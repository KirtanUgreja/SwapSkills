CREATE DATABASE swapskills;
USE swapskills;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    profile_image VARCHAR(255),
    location VARCHAR(100),
    availability TEXT,
    bio TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'beginner',
    is_offered BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE swap_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    provider_id INT NOT NULL,
    requester_skill_id INT,
    provider_skill_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed') DEFAULT 'pending',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_skill_id) REFERENCES skills(id) ON DELETE SET NULL,
    FOREIGN KEY (provider_skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    swap_request_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (swap_request_id) REFERENCES swap_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO skills (user_id, name, description, category, level, is_offered) VALUES
(1, 'Web Development', 'Full-stack web development with modern frameworks', 'Technology', 'expert', TRUE),
(1, 'Graphic Design', 'UI/UX design and branding', 'Design', 'intermediate', TRUE),
(1, 'Photography', 'Want to learn portrait photography', 'Creative', 'beginner', FALSE),
(2, 'Digital Marketing', 'Social media marketing and SEO', 'Marketing', 'expert', TRUE),
(2, 'Content Writing', 'Blog posts and copywriting', 'Writing', 'expert', TRUE),
(2, 'Coding', 'Want to learn programming basics', 'Technology', 'beginner', FALSE),
(3, 'Personal Training', 'Fitness coaching and workout plans', 'Health', 'expert', TRUE),
(3, 'Nutrition Planning', 'Meal planning and dietary advice', 'Health', 'intermediate', TRUE),
(3, 'Web Design', 'Want to learn modern web design', 'Technology', 'beginner', FALSE);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);
