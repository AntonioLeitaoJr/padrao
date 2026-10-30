-- Estruturas adicionais. Não apaga nem converte votos, seleções ou homologações.
CREATE TABLE IF NOT EXISTS fase4_ciclos (
 pleito_id INT PRIMARY KEY,
 categorias_json LONGTEXT NOT NULL,
 encerrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 encerrado_por INT NOT NULL,
 aberto_em DATETIME NULL,
 FOREIGN KEY (pleito_id) REFERENCES pleitos(id) ON DELETE CASCADE,
 FOREIGN KEY (encerrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fase4_decisoes_corte (
 pleito_id INT NOT NULL,
 categoria VARCHAR(20) NOT NULL,
 candidatos_json TEXT NOT NULL,
 justificativa TEXT NOT NULL,
 usuario_id INT NOT NULL,
 confirmado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (pleito_id,categoria),
 FOREIGN KEY (pleito_id) REFERENCES fase4_ciclos(pleito_id) ON DELETE CASCADE,
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fase4_avaliadores (
 pleito_id INT NOT NULL,
 usuario_id INT NOT NULL,
 nome_guerra VARCHAR(100) NOT NULL,
 posto VARCHAR(30) NOT NULL,
 PRIMARY KEY (pleito_id,usuario_id),
 FOREIGN KEY (pleito_id) REFERENCES fase4_ciclos(pleito_id) ON DELETE CASCADE,
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fase4_confirmacoes (
 pleito_id INT NOT NULL,
 usuario_id INT NOT NULL,
 confirmado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (pleito_id,usuario_id),
 FOREIGN KEY (pleito_id,usuario_id) REFERENCES fase4_avaliadores(pleito_id,usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fase4_ordenacoes (
 pleito_id INT NOT NULL,
 usuario_id INT NOT NULL,
 categoria VARCHAR(20) NOT NULL,
 candidato_id INT NOT NULL,
 posicao TINYINT UNSIGNED NOT NULL,
 justificativa TEXT NOT NULL,
 PRIMARY KEY (pleito_id,usuario_id,categoria,candidato_id),
 UNIQUE KEY uk_fase4_posicao (pleito_id,usuario_id,categoria,posicao),
 FOREIGN KEY (pleito_id,usuario_id) REFERENCES fase4_confirmacoes(pleito_id,usuario_id) ON DELETE CASCADE,
 FOREIGN KEY (candidato_id) REFERENCES efetivo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
