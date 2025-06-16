-- Requête SQL pour créer la table 'messages'
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion d'un message initial
INSERT INTO messages (content) VALUES ('ayoub the boss');
INSERT INTO messages (content) VALUES ('piplo the boss');
INSERT INTO messages (content) VALUES ('mariam the boss');
INSERT INTO messages (content) VALUES ('mohamad the boss');