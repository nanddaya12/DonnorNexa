<?php
declare(strict_types=1);

namespace DonorNexa\Contracts;

interface ScoringStrategy
{
    public function score(array $donor, array $request): float;
}

namespace DonorNexa\Services;

use DateTimeImmutable;
use DonorNexa\Contracts\ScoringStrategy;
use DonorNexa\Core\Config;
use DonorNexa\Core\Database;
use DonorNexa\Models\BloodRequest;
use DonorNexa\Models\Donor;
use DonorNexa\Repositories\MatchRepository;
use DonorNexa\Repositories\NotificationRepository;
use DonorNexa\Repositories\SettingRepository;
use DonorNexa\Traits\Auditable;
use PDO;

class CompatibilityService
{
    private static ?array $map = null;

    private function map(): array
    {
        if (self::$map === null) {
            self::$map = [];
            $rows = Database::connection()->query('SELECT donor_group, recipient_group FROM blood_compatibility')->fetchAll();
            foreach ($rows as $row) {
                self::$map[$row['donor_group']][] = $row['recipient_group'];
            }
        }
        return self::$map;
    }

    public function canDonate(string $donorGroup, string $recipientGroup): bool
    {
        return in_array($recipientGroup, $this->map()[$donorGroup] ?? [], true);
    }

    public function donorGroupsFor(string $recipientGroup): array
    {
        $groups = [];
        foreach ($this->map() as $donor => $recipients) {
            if (in_array($recipientGroup, $recipients, true)) {
                $groups[] = $donor;
            }
        }
        return $groups;
    }

    public function recipientsFor(string $donorGroup): array
    {
        return $this->map()[$donorGroup] ?? [];
    }
}

class DefaultScoringStrategy implements ScoringStrategy
{
    public function score(array $donor, array $request): float
    {
        $w = Config::get('app.scoring');
        $score = 0.0;

        if ($donor['blood_group'] === $request['blood_group']) {
            $score += $w['exact_group'];
        }
        if ((int) $donor['city_id'] === (int) $request['city_id']) {
            $score += $w['same_city'];
        }

        $days = min((int) $donor['days_since_donation'], (int) $w['rest_cap']);
        $score += $w['rest_days'] * ($days / $w['rest_cap']);
        $score += $w['response'] * (float) ($donor['response_rate'] ?? 0.5);

        return round($score, 2);
    }
}

class EligibilityService
{
    public const MIN_AGE = 18;
    public const MAX_AGE = 60;
    public const MIN_WEIGHT = 50;

    public function __construct(private ?SettingRepository $settings = null)
    {
        $this->settings ??= new SettingRepository();
    }

    public function cooldownDays(): int
    {
        return $this->settings->get('cooldown_days', 90);
    }

    public function check(Donor $donor): array
    {
        $reasons = [];
        $age = $donor->age();
        if ($age < self::MIN_AGE || $age > self::MAX_AGE) {
            $reasons[] = 'Donors must be between ' . self::MIN_AGE . ' and ' . self::MAX_AGE . ' years old.';
        }
        if ((float) $donor->weight < self::MIN_WEIGHT) {
            $reasons[] = 'Minimum weight to donate is ' . self::MIN_WEIGHT . ' kg.';
        }
        if (!(int) $donor->is_available) {
            $reasons[] = 'Your availability is switched off.';
        }
        if ($donor->status !== 'active') {
            $reasons[] = 'Your account is not active.';
        }

        $next = $donor->eligibleOn($this->cooldownDays());
        if ($next !== null && $next > new DateTimeImmutable('today')) {
            $reasons[] = 'You can donate again on ' . $next->format('d M Y') . '.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons, 'next_date' => $next];
    }
}

class MatchingService
{
    use Auditable;

    private ScoringStrategy $scorer;

    public function __construct(?ScoringStrategy $scorer = null)
    {
        $this->scorer = $scorer ?? new DefaultScoringStrategy();
    }

    public function notifyBatch(BloodRequest $request): int
    {
        $rules = Config::get('app.urgency')[$request->urgency];
        $batchSize = (new SettingRepository())->get('top_n', 5) * $rules['multiplier'];

        $candidates = $this->candidates($request, $rules['scope']);
        $matches = new MatchRepository();
        foreach ($candidates as &$c) {
            $c['response_rate'] = $matches->responseRate((int) $c['donor_id']);
            $c['score'] = $this->scorer->score($c, $request->toArray());
        }
        unset($c);

        usort($candidates, static fn(array $a, array $b) => $b['score'] <=> $a['score']);
        $top = array_slice($candidates, 0, $batchSize);

        $notifications = new NotificationRepository();
        foreach ($top as $donor) {
            if ($matches->insert((int) $request->id, (int) $donor['donor_id'], $donor['score'])) {
                $notifications->add(
                    (int) $donor['donor_id'],
                    sprintf(
                        '%s: %s blood needed in %s. Open your dashboard to accept or decline.',
                        $request->urgency,
                        $request->blood_group,
                        $request->city_name
                    )
                );
            }
        }
        $this->audit('donors_notified', 'blood_requests:' . $request->id, null, count($top) . ' donors');
        return count($top);
    }

    private function candidates(BloodRequest $request, string $scope): array
    {
        $groups = (new CompatibilityService())->donorGroupsFor($request->blood_group);
        if ($groups === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($groups), '?'));
        $params = $groups;

        $sql = "SELECT v.* FROM v_eligible_donors v
                 WHERE v.blood_group IN ($in)
                   AND NOT EXISTS (SELECT 1 FROM request_matches x WHERE x.request_id = ? AND x.donor_id = v.donor_id)
                   AND NOT EXISTS (SELECT 1 FROM request_matches m JOIN blood_requests br ON br.id = m.request_id
                                    WHERE m.donor_id = v.donor_id AND m.status = 'accepted'
                                      AND br.status IN ('open','fulfilled'))";
        $params[] = (int) $request->id;

        if ($scope === 'city') {
            $sql .= ' AND v.city_id = ?';
            $params[] = (int) $request->city_id;
        } elseif ($scope === 'province') {
            $sql .= ' AND v.province = ?';
            $params[] = $request->province;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
