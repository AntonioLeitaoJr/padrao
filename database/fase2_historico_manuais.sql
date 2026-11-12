-- Histórico nominal de vencedores que não constam do cadastro de efetivo.
-- A tabela é independente dos candidatos do pleito vigente.
CREATE TABLE IF NOT EXISTS historico_vencedores_manuais (
    ano INT NOT NULL,
    categoria ENUM('graduado','praca','sppf','sptf') NOT NULL,
    nome_vencedor VARCHAR(255) NOT NULL,
    atualizado_por INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ano, categoria),
    FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
