CREATE TABLE IF NOT EXISTS focus_rift_sessions (
    token CHAR(64) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    goal_id INT DEFAULT NULL,
    rift_key VARCHAR(30) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    INDEX idx_focus_user (user_id, started_at),
    CONSTRAINT fk_focus_session_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_focus_session_goal FOREIGN KEY (goal_id) REFERENCES life_goals(goal_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
