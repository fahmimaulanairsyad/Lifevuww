CREATE TABLE IF NOT EXISTS companion_chronicle (
    event_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    companion_key VARCHAR(40) NOT NULL,
    event_key VARCHAR(100) NOT NULL,
    stage VARCHAR(20) NOT NULL,
    bond_exp INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_companion_event (user_id,companion_key,event_key),
    CONSTRAINT fk_chronicle_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
