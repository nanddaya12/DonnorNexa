-- =====================================================================
-- DonorNexa - queries.sql : JOIN / aggregate / subquery / view examples
-- (for the "at least five meaningful queries" report requirement)
-- Run after schema.sql + seed.sql:  mysql -u root donornexa < sql/queries.sql
-- =====================================================================
USE donornexa;

-- Q1. JOIN + GROUP BY: fulfilment performance per hospital
SELECT h.hospital_name, c.city_name,
       COUNT(r.id)                                           AS total_requests,
       SUM(r.status IN ('fulfilled','closed'))               AS successful,
       ROUND(100 * SUM(r.status IN ('fulfilled','closed')) / COUNT(r.id), 1) AS success_pct,
       h.trust_score
FROM hospitals h
JOIN cities c          ON c.id = h.city_id
LEFT JOIN blood_requests r ON r.hospital_id = h.user_id
GROUP BY h.user_id, h.hospital_name, c.city_name, h.trust_score
ORDER BY success_pct DESC;

-- Q2. View: eligible donors available right now, by blood group and city
SELECT blood_group, city_name, COUNT(*) AS eligible_donors
FROM v_eligible_donors
GROUP BY blood_group, city_name
ORDER BY eligible_donors DESC, blood_group;

-- Q3. Aggregate with HAVING: blood groups where open demand exceeds eligible supply
SELECT r.blood_group,
       SUM(r.units_needed - r.units_fulfilled) AS units_still_needed,
       (SELECT COUNT(*) FROM v_eligible_donors v WHERE v.blood_group = r.blood_group) AS eligible_same_group
FROM blood_requests r
WHERE r.status = 'open'
GROUP BY r.blood_group
HAVING units_still_needed > eligible_same_group;

-- Q4. Multi-table JOIN: donor leaderboard (donations, last donation, response behaviour)
SELECT u.name, d.blood_group, ci.city_name,
       COUNT(dn.id)                                            AS donations,
       MAX(dn.donated_on)                                      AS last_donated,
       ROUND(100 * SUM(m.status IN ('accepted','donated')) /
             NULLIF(SUM(m.status IN ('accepted','donated','declined','no_response')), 0), 0) AS response_pct
FROM donors d
JOIN users u            ON u.id = d.user_id
JOIN cities ci          ON ci.id = d.city_id
LEFT JOIN request_matches m ON m.donor_id = d.user_id
LEFT JOIN donations dn      ON dn.match_id = m.id
GROUP BY d.user_id, u.name, d.blood_group, ci.city_name
HAVING donations > 0
ORDER BY donations DESC, last_donated DESC;

-- Q5. Subquery: donors who were notified at least 3 times but never accepted (donor-fatigue check)
SELECT u.name, d.blood_group, x.times_notified
FROM (SELECT donor_id, COUNT(*) AS times_notified,
             SUM(status IN ('accepted','donated')) AS yes
      FROM request_matches GROUP BY donor_id) x
JOIN donors d ON d.user_id = x.donor_id
JOIN users u  ON u.id = d.user_id
WHERE x.times_notified >= 3 AND x.yes = 0;

-- Q6. Time series: requests created per week for the last 8 weeks
SELECT DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_start,
       COUNT(*) AS requests,
       SUM(urgency = 'Critical') AS critical
FROM blood_requests
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
GROUP BY week_start
ORDER BY week_start;

-- Q7. Compatibility map: which donor groups can serve an AB- patient
SELECT donor_group FROM blood_compatibility WHERE recipient_group = 'AB-' ORDER BY donor_group;

-- Q8. Audit trail for one request (every status change with who and when)
SELECT a.created_at, COALESCE(u.name, 'system') AS actor, a.action, a.old_value, a.new_value
FROM audit_logs a
LEFT JOIN users u ON u.id = a.user_id
WHERE a.entity = 'blood_requests:16'
ORDER BY a.id;

-- ---------------------------------------------------------------------
-- Explicit transaction (same pattern the PHP code runs in RequestService::accept)
-- Two sessions running this at once cannot over-allocate: the second waits on FOR UPDATE.
-- ---------------------------------------------------------------------
-- START TRANSACTION;
--   SELECT units_needed, units_fulfilled FROM blood_requests WHERE id = 1 FOR UPDATE;
--   -- (application checks units_fulfilled < units_needed)
--   UPDATE request_matches SET status = 'accepted', responded_at = NOW() WHERE id = 1 AND status = 'notified';
--   UPDATE blood_requests SET units_fulfilled = units_fulfilled + 1 WHERE id = 1;
-- COMMIT;   -- or ROLLBACK on any error
