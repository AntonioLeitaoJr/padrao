-- Retrato somente de novas homologações. Não recalcula nem converte as antigas.
CREATE TABLE IF NOT EXISTS fase5_resultados (
 pleito_id INT PRIMARY KEY,
 resultado_json LONGTEXT NOT NULL,
 registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (pleito_id) REFERENCES homologacao_pleito(pleito_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
