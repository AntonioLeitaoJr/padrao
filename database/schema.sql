-- =============================================================================
-- SISTEMA DE ELEIÇÃO DOS PADRÕES MILITAR E CIVIL DA COMARA
-- Banco de Dados Oficial: MySQL 8.0+ / MariaDB 10.11+ (Debian 13 / aaPanel)
-- Suporte a UTF8MB4 e Integridade Referencial Estrita
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABELA DE PLEITOS (CICLOS ANUAIS)
CREATE TABLE IF NOT EXISTS `pleitos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ano` INT NOT NULL UNIQUE,
    `titulo` VARCHAR(255) NOT NULL,
    `descricao` TEXT NULL,
    `fase_atual` TINYINT NOT NULL DEFAULT 1,
    `status` ENUM('planejamento', 'em_andamento', 'homologado', 'cancelado') NOT NULL DEFAULT 'em_andamento',
    `fase1_inicio` DATETIME NULL,
    `fase1_fim` DATETIME NULL,
    `fase2_inicio` DATETIME NULL,
    `fase2_fim` DATETIME NULL,
    `fase3_inicio` DATETIME NULL,
    `fase3_fim` DATETIME NULL,
    `fase4_inicio` DATETIME NULL,
    `fase4_fim` DATETIME NULL,
    `fase5_inicio` DATETIME NULL,
    `fase5_fim` DATETIME NULL,
    `homologado_em` DATETIME NULL,
    `homologado_por` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pleito_status` (`status`, `ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABELA DE DIVISÕES E ASSESSORIAS DA COMARA
CREATE TABLE IF NOT EXISTS `divisoes_assessorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sigla` VARCHAR(20) NOT NULL UNIQUE,
    `nome` VARCHAR(255) NOT NULL,
    `tipo` ENUM('DIVISAO', 'ASSESSORIA', 'PRESIDENCIA', 'OUTRO') NOT NULL,
    `chefe_usuario_id` INT NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABELA DE EFETIVO (MILITARES E CIVIS DA COMARA)
CREATE TABLE IF NOT EXISTS `efetivo` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `saram` VARCHAR(20) NULL,
    `cpf` VARCHAR(14) NULL,
    `cpf_hash` VARCHAR(64) NULL,
    `nome` VARCHAR(255) NOT NULL,
    `nome_guerra` VARCHAR(100) NULL,
    `posto` VARCHAR(30) NOT NULL, -- Cel, Ten Cel, Maj, Cap, 1º Ten, 2º Ten, SO, 1S, 2S, 3S, Cb, S1, S2, CV
    `quadro` VARCHAR(50) NULL,
    `especialidade` VARCHAR(50) NULL,
    `setor` VARCHAR(100) NULL,
    `divisao_sigla` VARCHAR(20) NULL,
    `funcao` VARCHAR(255) NULL,
    `categoria` ENUM('graduado', 'praca', 'sppf', 'sptf', 'ineligivel') NOT NULL,
    `tipo_vinculo` ENUM('MILITAR_CARREIRA', 'MILITAR_TEMPORARIO', 'CIVIL_PERMANENTE', 'CIVIL_TEMPORARIO') NOT NULL,
    `chefe_direto_id` INT NULL,
    `data_admissao_comara` DATE NULL, -- Critério de desempate 2: Mais antigo na COMARA
    `data_vinculo_om` DATE NULL,      -- Critério de desempate 3: Maior tempo de OM atual
    `indicacoes_anteriores_qtd` INT NOT NULL DEFAULT 0, -- Critério 1: Nunca indicado antes
    `foto_custom` VARCHAR(255) NULL,  -- Upload manual de foto pelo administrador
    `telefone` VARCHAR(50) NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_efetivo_categoria` (`categoria`),
    INDEX `idx_efetivo_saram` (`saram`),
    INDEX `idx_efetivo_cpf_hash` (`cpf_hash`),
    INDEX `idx_efetivo_chefe` (`chefe_direto_id`),
    INDEX `idx_efetivo_setor` (`setor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABELA DE USUÁRIOS DO SISTEMA
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `login` VARCHAR(100) NOT NULL UNIQUE,
    `senha_hash` VARCHAR(255) NULL,
    `efetivo_id` INT NULL UNIQUE,
    `perfil` ENUM('ADMIN', 'RH', 'PRESIDENTE', 'DIRECAO_SUPERIOR', 'CHEFE_DIV_ASSESS', 'CHEFE_DIRETO', 'ED_FISICA', 'ELEITOR') NOT NULL DEFAULT 'ELEITOR',
    `orgao_chefe_sigla` VARCHAR(20) NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `ultimo_login` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_usuarios_efetivo` FOREIGN KEY (`efetivo_id`) REFERENCES `efetivo` (`id`) ON DELETE SET NULL,
    INDEX `idx_usuarios_perfil` (`perfil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABELA DE FICHAS DE INDICAÇÃO (FASE 1)
CREATE TABLE IF NOT EXISTS `fichas_indicacao` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `candidato_id` INT NOT NULL,
    `avaliador_id` INT NOT NULL,
    `tipo_avaliacao` ENUM('OBRIGATORIA_CHEFE', 'VOLUNTARIA') NOT NULL DEFAULT 'OBRIGATORIA_CHEFE',
    `is_nob` TINYINT(1) NOT NULL DEFAULT 0,
    `servico_escala` VARCHAR(255) NULL,
    `eficiencia_eficacia` TINYINT NULL CHECK (`eficiencia_eficacia` IS NULL OR (`eficiencia_eficacia` BETWEEN 0 AND 5)),
    `conhecimento_especialidade` TINYINT NULL CHECK (`conhecimento_especialidade` IS NULL OR (`conhecimento_especialidade` BETWEEN 0 AND 5)),
    `iniciativa_adaptabilidade` TINYINT NULL CHECK (`iniciativa_adaptabilidade` IS NULL OR (`iniciativa_adaptabilidade` BETWEEN 0 AND 5)),
    `conduta_pontuacao` TINYINT NULL CHECK (`conduta_pontuacao` IS NULL OR (`conduta_pontuacao` BETWEEN 0 AND 5)),
    `conduta_fichas` TEXT NULL,
    `apresentacao_pessoal` TINYINT NULL CHECK (`apresentacao_pessoal` IS NULL OR (`apresentacao_pessoal` BETWEEN 0 AND 5)),
    `relacionamento_trabalho` TINYINT NULL CHECK (`relacionamento_trabalho` IS NULL OR (`relacionamento_trabalho` BETWEEN 0 AND 5)),
    `lideranca` TINYINT NULL CHECK (`lideranca` IS NULL OR (`lideranca` BETWEEN 0 AND 5)),
    `justificativa` TEXT NULL,
    `media_calculada` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    `hash_integridade` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_fichas_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fichas_candidato` FOREIGN KEY (`candidato_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fichas_avaliador` FOREIGN KEY (`avaliador_id`) REFERENCES `usuarios` (`id`),
    UNIQUE KEY `uk_candidato_avaliador` (`pleito_id`, `candidato_id`, `avaliador_id`),
    INDEX `idx_fichas_candidato` (`candidato_id`, `pleito_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABELA DE AVALIAÇÕES TACF (EXCLUSIVO SETOR DE EDUCAÇÃO FÍSICA)
CREATE TABLE IF NOT EXISTS `avaliacoes_tacf` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `candidato_id` INT NOT NULL,
    `avaliador_ed_fisica_id` INT NOT NULL,
    `nota_tacf` DECIMAL(4,2) NOT NULL CHECK (`nota_tacf` >= 1.00 AND `nota_tacf` <= 5.00),
    `data_realizacao` DATE NULL,
    `observacoes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tacf_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tacf_candidato` FOREIGN KEY (`candidato_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tacf_avaliador` FOREIGN KEY (`avaliador_ed_fisica_id`) REFERENCES `usuarios` (`id`),
    UNIQUE KEY `uk_tacf_pleito_candidato` (`pleito_id`, `candidato_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABELA DE SELEÇÃO POR DIVISÃO / ASSESSORIA (FASE 2)
-- Cada chefe deve escolher EXATAMENTE 6 por categoria
CREATE TABLE IF NOT EXISTS `selecao_fase2` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `orgao_sigla` VARCHAR(20) NOT NULL,
    `chefe_usuario_id` INT NOT NULL,
    `categoria` ENUM('graduado', 'praca', 'sppf', 'sptf') NOT NULL,
    `candidato_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_fase2_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fase2_chefe` FOREIGN KEY (`chefe_usuario_id`) REFERENCES `usuarios` (`id`),
    CONSTRAINT `fk_fase2_candidato` FOREIGN KEY (`candidato_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_fase2_escolha` (`pleito_id`, `orgao_sigla`, `categoria`, `candidato_id`),
    INDEX `idx_fase2_orgao` (`pleito_id`, `orgao_sigla`, `categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABELA DE INDICADOS CONSOLIDADOS (TRANSITAM PARA FASE 3/4)
CREATE TABLE IF NOT EXISTS `indicados_consolidados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `candidato_id` INT NOT NULL,
    `categoria` ENUM('graduado', 'praca', 'sppf', 'sptf') NOT NULL,
    `total_indicacoes_orgaos` INT NOT NULL DEFAULT 0,
    `media_fase1` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    `status_validacao_superior` ENUM('VALIDADO', 'EXCLUIDO') NOT NULL DEFAULT 'VALIDADO',
    `justificativa_exclusao` TEXT NULL,
    `excluido_por_usuario_id` INT NULL,
    `excluido_em` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_indicados_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_indicados_candidato` FOREIGN KEY (`candidato_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_indicados_excluidor` FOREIGN KEY (`excluido_por_usuario_id`) REFERENCES `usuarios` (`id`),
    UNIQUE KEY `uk_indicados_pleito_cand` (`pleito_id`, `candidato_id`),
    INDEX `idx_indicados_categoria` (`pleito_id`, `categoria`, `status_validacao_superior`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ARQUITETURA DE URNA ELETRÔNICA SIGILOSA (FASE 3)
-- Desacoplamento Absoluto entre o Eleitor e o Voto Computado
CREATE TABLE IF NOT EXISTS `eleitores_ciclo` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `usuario_id` INT NULL,
    `cpf_hash` VARCHAR(64) NOT NULL,
    `votou` TINYINT(1) NOT NULL DEFAULT 0,
    `votou_em` DATETIME NULL,
    `codigo_comprovante` VARCHAR(64) NULL,
    CONSTRAINT `fk_eleitores_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_eleitor_cpf_pleito` (`pleito_id`, `cpf_hash`),
    INDEX `idx_eleitor_status` (`pleito_id`, `votou`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Urna de Votos: SEM CHAVE ESTRANGEIRA OU PONTEIRO PARA O ELEITOR
CREATE TABLE IF NOT EXISTS `urna_votos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `categoria` ENUM('graduado', 'praca', 'sppf', 'sptf') NOT NULL,
    `candidato_id` INT NOT NULL,
    `horario_arredondado` DATETIME NOT NULL,
    CONSTRAINT `fk_urna_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_urna_candidato` FOREIGN KEY (`candidato_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    INDEX `idx_urna_contagem` (`pleito_id`, `categoria`, `candidato_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. VOTO DE MINERVA DO PRESIDENTE (FASE 5)
CREATE TABLE IF NOT EXISTS `voto_minerva_presidente` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `categoria` ENUM('graduado', 'praca', 'sppf', 'sptf') NOT NULL,
    `candidato_vencedor_id` INT NOT NULL,
    `justificativa` TEXT NOT NULL,
    `presidente_usuario_id` INT NOT NULL,
    `registrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_minerva_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_minerva_candidato` FOREIGN KEY (`candidato_vencedor_id`) REFERENCES `efetivo` (`id`),
    CONSTRAINT `fk_minerva_presidente` FOREIGN KEY (`presidente_usuario_id`) REFERENCES `usuarios` (`id`),
    UNIQUE KEY `uk_minerva_pleito_cat` (`pleito_id`, `categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. HOMOLOGAÇÃO OFICIAL DO PLEITO (FASE 5)
CREATE TABLE IF NOT EXISTS `homologacao_pleito` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL UNIQUE,
    `presidente_usuario_id` INT NOT NULL,
    `vencedor_graduado_id` INT NOT NULL,
    `vencedor_praca_id` INT NOT NULL,
    `vencedor_sppf_id` INT NOT NULL,
    `vencedor_sptf_id` INT NOT NULL,
    `ata_homologacao` TEXT NOT NULL,
    `data_homologacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `bloqueado` TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT `fk_homol_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_homol_presidente` FOREIGN KEY (`presidente_usuario_id`) REFERENCES `usuarios` (`id`),
    CONSTRAINT `fk_homol_grad` FOREIGN KEY (`vencedor_graduado_id`) REFERENCES `efetivo` (`id`),
    CONSTRAINT `fk_homol_praca` FOREIGN KEY (`vencedor_praca_id`) REFERENCES `efetivo` (`id`),
    CONSTRAINT `fk_homol_sppf` FOREIGN KEY (`vencedor_sppf_id`) REFERENCES `efetivo` (`id`),
    CONSTRAINT `fk_homol_sptf` FOREIGN KEY (`vencedor_sptf_id`) REFERENCES `efetivo` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. LOGS DE AUDITORIA PERENES (RETENÇÃO MÍNIMA DE 5 ANOS)
CREATE TABLE IF NOT EXISTS `logs_auditoria` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NULL,
    `login` VARCHAR(100) NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `acao` VARCHAR(100) NOT NULL,
    `detalhes_json` JSON NULL,
    `hash_anterior` VARCHAR(64) NULL,
    `hash_registro` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_auditoria_data` (`created_at`),
    INDEX `idx_auditoria_usuario` (`usuario_id`, `acao`),
    INDEX `idx_auditoria_acao` (`acao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TABELA DE DELEGAÇÕES DE AVALIAÇÃO DA FASE 1 (OFICIAL EM MISSÃO)
CREATE TABLE IF NOT EXISTS `delegacoes_fase1` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `candidato_id` INT NOT NULL,
    `oficial_delegado_id` INT NOT NULL,
    `designado_por_id` INT NOT NULL,
    `motivo` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_deleg_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_deleg_cand` FOREIGN KEY (`candidato_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_deleg_oficial` FOREIGN KEY (`oficial_delegado_id`) REFERENCES `efetivo` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_deleg_user` FOREIGN KEY (`designado_por_id`) REFERENCES `usuarios` (`id`),
    UNIQUE KEY `uk_deleg_pleito_cand` (`pleito_id`, `candidato_id`),
    INDEX `idx_deleg_oficial` (`pleito_id`, `oficial_delegado_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. HISTÓRICO DE TRANSIÇÕES DE FASES DO PLEITO COM AUDITORIA
CREATE TABLE IF NOT EXISTS `historico_fases_pleito` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pleito_id` INT NOT NULL,
    `fase_de` INT NOT NULL,
    `fase_para` INT NOT NULL,
    `tipo_transicao` ENUM('AVANCO', 'RETORNO') NOT NULL DEFAULT 'AVANCO',
    `teve_pendencias` TINYINT(1) NOT NULL DEFAULT 0,
    `total_pendencias` INT NOT NULL DEFAULT 0,
    `justificativa` TEXT NULL,
    `usuario_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hist_pleito` FOREIGN KEY (`pleito_id`) REFERENCES `pleitos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hist_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
    INDEX `idx_hist_pleito` (`pleito_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

