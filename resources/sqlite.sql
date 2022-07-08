-- #!mysql
-- # { advancedban
-- #   { init_name_ban
CREATE TABLE IF NOT EXISTS name_ban (name VARCHAR(30) NOT NULL PRIMARY KEY, expireAt BIGINT NOT NULL, reason VARCHAR(255) NOT NULL, issuer VARCHAR(30) NOT NULL)
-- #   }
-- #   { init_device_ban
CREATE TABLE IF NOT EXISTS device_ban (deviceIds VARCHAR(30) NOT NULL PRIMARY KEY, expireAt BIGINT NOT NULL, reason VARCHAR(255) NOT NULL, issuer VARCHAR(30) NOT NULL)
-- #   }
-- #   { init_player
CREATE TABLE IF NOT EXISTS session (name VARCHAR(30) NOT NULL PRIMARY KEY, deviceIds TEXT NOT NULL)
-- #   }
-- #   { ban_name
-- #     :name string
-- #     :expireAt int
-- #     :reason string
-- #     :issuer string
INSERT INTO name_ban (name, expireAt, reason, issuer) VALUES (:name, :expireAt, :reason, :issuer)
-- #   }
-- #   { pardon_name
-- #     :name string
DELETE FROM name_ban WHERE name = :name
-- #   }
-- #   { is_banned_name
-- #     :name string
SELECT * FROM name_ban WHERE name = :name
-- #   }
-- #   { ban_device
-- #     :deviceId string
-- #     :expireAt int
-- #     :reason string
-- #     :issuer string
INSERT INTO device_ban (deviceId, expireAt, reason, issuer) VALUES (:deviceId, :expireAt, :reason, :issuer)
-- #   }
-- #   { pardon_device
-- #     :deviceId string
DELETE FROM device_ban WHERE deviceId = :deviceId
-- #   }
-- #   { is_banned_device
-- #     :deviceId string
SELECT * FROM device_ban WHERE deviceId = :deviceId
-- #   }
-- #   { create_session
-- #     :name string
-- #     :deviceIds string
INSERT INTO session (name, deviceIds) VALUES (:name, :deviceIds)
-- #   }
-- #   { update_session
-- #     :name string
-- #     :deviceIds string
UPDATE session SET deviceIds = :deviceIds WHERE name = :name
-- #   }
-- #   { get_session
-- #     :name string
SELECT * FROM session WHERE name = :name
-- #   }
-- # }