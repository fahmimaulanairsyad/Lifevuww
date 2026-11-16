CREATE TABLE IF NOT EXISTS goal_activity_progress (
    user_id INT NOT NULL,
    kind VARCHAR(10) NOT NULL,
    activity_id INT NOT NULL,
    goal_id INT NOT NULL,
    milestone_id INT NOT NULL,
    advanced_at DATETIME DEFAULT NULL,
    PRIMARY KEY (user_id, kind, activity_id, goal_id),
    UNIQUE KEY uniq_user_milestone (user_id, milestone_id),
    CONSTRAINT fk_goal_progress_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_goal_progress_goal FOREIGN KEY (goal_id) REFERENCES life_goals(goal_id) ON DELETE CASCADE,
    CONSTRAINT fk_goal_progress_milestone FOREIGN KEY (milestone_id) REFERENCES goal_milestones(milestone_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
