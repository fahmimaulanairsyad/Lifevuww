CREATE TABLE IF NOT EXISTS life_goals (
    goal_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'Intelligence',
    target_date DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_goal_user (user_id),
    CONSTRAINT fk_life_goal_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goal_milestones (
    milestone_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    completed TINYINT NOT NULL DEFAULT 0,
    INDEX idx_milestone_goal (goal_id),
    CONSTRAINT fk_milestone_goal FOREIGN KEY (goal_id) REFERENCES life_goals(goal_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goal_activities (
    goal_id INT NOT NULL,
    kind VARCHAR(10) NOT NULL,
    activity_id INT NOT NULL,
    PRIMARY KEY (goal_id, kind, activity_id),
    CONSTRAINT fk_goal_activity_goal FOREIGN KEY (goal_id) REFERENCES life_goals(goal_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planned_breaks (
    break_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    INDEX idx_break_user_dates (user_id, start_date, end_date),
    CONSTRAINT fk_planned_break_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goal_focus_sessions (
    focus_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    goal_id INT NOT NULL,
    duration_seconds INT NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_goal_focus (user_id, goal_id),
    CONSTRAINT fk_goal_focus_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_goal_focus_goal FOREIGN KEY (goal_id) REFERENCES life_goals(goal_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
