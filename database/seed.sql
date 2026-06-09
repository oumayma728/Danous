USE danous;

-- Default admin account (password: admin123)
INSERT INTO users (name, email, password_hash, role, status) VALUES
('Admin', 'oumaimajl88@gmail.com', '$2y$10$79a9AdzmYkuhxfcbn3CJn.8LzCDVKEf8.SoBsSuPYoJUqvRrmmk2i', 'admin', 'active');

-- Default categories (user_id NULL = available to everyone)
INSERT INTO categories (user_id, name, is_default) VALUES
(NULL, 'Food & groceries', TRUE),
(NULL, 'Transport', TRUE),
(NULL, 'Housing & rent', TRUE),
(NULL, 'Health', TRUE),
(NULL, 'Leisure', TRUE),
(NULL, 'Education', TRUE),
(NULL, 'Clothing', TRUE),
(NULL, 'Savings', TRUE),
(NULL, 'Other', TRUE);
