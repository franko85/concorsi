-- =====================================================================
-- init.sql - Schema & seed per "concorsi" con requisiti (MySQL 8)
-- Esegue su DATABASE già selezionato via MYSQL_DATABASE (es. 'concorsi')
-- =====================================================================

-- Impostazioni consigliate per sviluppo (facoltative)
SET NAMES utf8mb4;
SET time_zone = '+01:00';
SET sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Verifica database corrente (facoltativo per debug)
-- SELECT DATABASE();

-- =====================================================================
-- TABELLE
-- =====================================================================

-- Tabella concorsi
CREATE TABLE IF NOT EXISTS concorsi
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titolo     VARCHAR(255) NOT NULL,
    ente       VARCHAR(255) NOT NULL,
    regione    VARCHAR(100) NOT NULL,
    categoria  VARCHAR(100) NOT NULL, -- es: "IT", "Amministrativo"
    scadenza   DATE         NOT NULL,
    link_bando VARCHAR(500) NOT NULL, -- usato come identificatore univoco
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indici
    UNIQUE KEY uq_concorso_link (link_bando),
    INDEX idx_scadenza (scadenza),
    INDEX idx_regione (regione),
    INDEX idx_categoria (categoria)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Tabella requisiti (anagrafica)
CREATE TABLE IF NOT EXISTS requisiti
(
    id          INT AUTO_INCREMENT PRIMARY KEY,
    codice      VARCHAR(50)  NOT NULL, -- es: "LM-66"
    descrizione VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_requisito_codice (codice)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Tabella ponte molti-a-molti tra concorsi e requisiti
CREATE TABLE IF NOT EXISTS concorso_requisito
(
    concorso_id  INT NOT NULL,
    requisito_id INT NOT NULL,
    PRIMARY KEY (concorso_id, requisito_id),
    CONSTRAINT fk_cr_concorso FOREIGN KEY (concorso_id)
        REFERENCES concorsi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cr_requisito FOREIGN KEY (requisito_id)
        REFERENCES requisiti (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Indici aggiuntivi per i filtri
CREATE INDEX idx_cr_requisito ON concorso_requisito (requisito_id);
CREATE INDEX idx_cr_concorso ON concorso_requisito (concorso_id);

-- (Opzionale) Fulltext su titolo/ente se vuoi attivare MATCH AGAINST:
-- ALTER TABLE concorsi ADD FULLTEXT KEY ft_titolo_ente (titolo, ente);

-- =====================================================================
-- SEED DATI DI ESEMPIO (idempotente)
-- =====================================================================

-- Concorsi di esempio
INSERT INTO concorsi (titolo, ente, regione, categoria, scadenza, link_bando)
VALUES ('Analista Programmatore', 'Comune di Firenze', 'Toscana', 'IT', DATE_ADD(CURDATE(), INTERVAL 30 DAY),
        'https://esempio.it/bando1'),
       ('Istruttore Amministrativo', 'ASL Lucca', 'Toscana', 'Amministrativo', DATE_ADD(CURDATE(), INTERVAL 20 DAY),
        'https://esempio.it/bando2'),
       ('Tecnico Sistemi', 'Regione Toscana', 'Toscana', 'IT', DATE_ADD(CURDATE(), INTERVAL 45 DAY),
        'https://esempio.it/bando3')
ON DUPLICATE KEY UPDATE titolo    = VALUES(titolo),
                        ente      = VALUES(ente),
                        regione   = VALUES(regione),
                        categoria = VALUES(categoria),
                        scadenza  = VALUES(scadenza);

-- Requisiti (codici comuni come LM-66, LM-18, ecc.)
INSERT INTO requisiti (codice, descrizione)
VALUES ('LM-66', 'Sicurezza Informatica'),
       ('LM-18', 'Informatica'),
       ('L-31', 'Scienze e Tecnologie Informatiche'),
       ('Diploma', 'Diploma di Scuola Superiore')
ON DUPLICATE KEY UPDATE descrizione = VALUES(descrizione);

-- Associazioni concorsi <-> requisiti
-- Usando link_bando come chiave stabile per referenziare i concorsi
INSERT INTO concorso_requisito (concorso_id, requisito_id)
SELECT c.id, r.id
FROM concorsi c
         JOIN requisiti r ON r.codice IN ('LM-66', 'LM-18')
WHERE c.link_bando = 'https://esempio.it/bando1'
ON DUPLICATE KEY UPDATE concorso_id = concorso_id;

INSERT INTO concorso_requisito (concorso_id, requisito_id)
SELECT c.id, r.id
FROM concorsi c
         JOIN requisiti r ON r.codice = 'Diploma'
WHERE c.link_bando = 'https://esempio.it/bando2'
ON DUPLICATE KEY UPDATE concorso_id = concorso_id;

INSERT INTO concorso_requisito (concorso_id, requisito_id)
SELECT c.id, r.id
FROM concorsi c
         JOIN requisiti r ON r.codice IN ('LM-66', 'L-31')
WHERE c.link_bando = 'https://esempio.it/bando3'
ON DUPLICATE KEY UPDATE concorso_id = concorso_id;

-- =====================================================================
-- VERIFICHE RAPIDE (facoltative)
-- =====================================================================

-- SELECT DATABASE();
-- SELECT * FROM concorsi;
-- SELECT * FROM requisiti;
-- SELECT c.titolo, GROUP_CONCAT(r.codice ORDER BY r.codice SEPARATOR ', ') AS requisiti
-- FROM concorsi c
-- LEFT JOIN concorso_requisito cr ON cr.concorso_id = c.id
-- LEFT JOIN requisiti r ON r.id = cr.requisito_id
-- GROUP BY c.id
-- ORDER BY c.scadenza ASC;