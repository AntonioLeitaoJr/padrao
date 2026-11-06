-- Retrato somente de novas homologações. Não recalcula nem converte as antigas.
CREATE TABLE IF NOT EXISTS fase5_resultados (
 pleito_id INT PRIMARY KEY,
 resultado_json LONGTEXT NOT NULL,
 registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (pleito_id) REFERENCES homologacao_pleito(pleito_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ordenações revisáveis da Presidência; o resultado oficial fica em fase5_resultados.
CREATE TABLE IF NOT EXISTS fase5_decisoes (
 pleito_id INT NOT NULL,
 categoria VARCHAR(20) NOT NULL,
 decisao_json LONGTEXT NOT NULL,
 justificativa TEXT NOT NULL,
 usuario_id INT NOT NULL,
 registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (pleito_id,categoria),
 FOREIGN KEY (pleito_id) REFERENCES pleitos(id) ON DELETE CASCADE,
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
