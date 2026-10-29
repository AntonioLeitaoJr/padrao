CREATE TABLE IF NOT EXISTS historico_vencedores (
    ano INT NOT NULL,
    categoria ENUM('graduado','praca','sppf','sptf') NOT NULL,
    candidato_id INT NOT NULL,
    atualizado_por INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ano, categoria),
    FOREIGN KEY (candidato_id) REFERENCES efetivo(id),
    FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
