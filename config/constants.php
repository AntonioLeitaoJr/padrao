<?php
declare(strict_types=1);

/**
 * Constantes Globais do Sistema de Votação dos Padrões COMARA
 * Força Aérea Brasileira - Comissão de Aeroportos da Região Amazônica
 */

// Categorias Oficiais
define('CAT_GRADUADO',  'graduado');   // Suboficiais (SO) e Sargentos (1S, 2S, 3S)
define('CAT_PRACA',      'praca');      // Cabos (CB) e Soldados (S1, S2)
define('CAT_SPPF',       'sppf');       // Servidores Públicos Civis Permanentes
define('CAT_SPTF',       'sptf');       // Servidores Públicos Civis Temporários
define('CAT_INELIGIVEL', 'ineligivel'); // Oficiais e funções não elegíveis a voto de padrão

const CATEGORIAS_PADRAO = [
    CAT_GRADUADO => [
        'id'        => CAT_GRADUADO,
        'nome'      => 'Graduado Padrão',
        'sigla'     => 'GRAD',
        'descricao' => 'Exclusivo para Suboficiais (SO) e Sargentos (1S, 2S, 3S)',
        'postos'    => ['SO', '1S', '2S', '3S']
    ],
    CAT_PRACA => [
        'id'        => CAT_PRACA,
        'nome'      => 'Praça Padrão',
        'sigla'     => 'PRAÇA',
        'descricao' => 'Exclusivo para Cabos (CB) e Soldados (S1, S2)',
        'postos'    => ['CB', 'Cb', 'S1', 'S2']
    ],
    CAT_SPPF => [
        'id'        => CAT_SPPF,
        'nome'      => 'Servidor Civil Permanente Padrão',
        'sigla'     => 'SPPF',
        'descricao' => 'Exclusivo para Servidores Públicos Civis Permanentes (Quadro Efetivo)',
        'postos'    => ['CV']
    ],
    CAT_SPTF => [
        'id'        => CAT_SPTF,
        'nome'      => 'Servidor Civil Temporário Padrão',
        'sigla'     => 'SPTF',
        'descricao' => 'Exclusivo para Servidores Públicos Civis Temporários (Lei nº 8.745/1993)',
        'postos'    => ['CV']
    ]
];

// Fases Regimentais
define('FASE_1', 1); // Avaliação pelos Chefes Diretos
define('FASE_2', 2); // Seleção pelos Chefes de Divisão e Assessoria (Um por categoria disponível em cada órgão)
define('FASE_3', 3); // Votação Geral do Efetivo
define('FASE_4', 4); // Estado-Maior (Apreciação por Oficiais Superiores)
define('FASE_5', 5); // Homologação dos Resultados e Voto de Minerva

const FASES_PROCESSO = [
    FASE_1 => [
        'numero'    => 1,
        'nome'      => 'Avaliação pelos Chefes Diretos',
        'descricao' => 'Preenchimento obrigatório da ficha pelos chefes diretos e lançamento exclusivo do TACF pela Ed. Física.'
    ],
    FASE_2 => [
        'numero'    => 2,
        'nome'      => 'Seleção por Divisões e Assessorias',
        'descricao' => 'Seleção de 1 candidato por categoria por cada Divisão e pelas Assessorias unificadas (até 6 indicados por categoria para a Fase 3).'
    ],
    FASE_3 => [
        'numero'    => 3,
        'nome'      => 'Votação Geral do Efetivo',
        'descricao' => 'Votação sigilosa em turno único por todo o efetivo com validação rigorosa de CPF único.'
    ],
    FASE_4 => [
        'numero'    => 4,
        'nome'      => 'Estado-Maior',
        'descricao' => 'Ordenação individual por Coronéis, Tenentes-Coronéis e Majores, com justificativas e pontos 5–3–1.'
    ],
    FASE_5 => [
        'numero'    => 5,
        'nome'      => 'Homologação e Desempate',
        'descricao' => 'Consolidação das ordenações, desempates, decisão excepcional por categoria vazia e homologação.'
    ]
];

// Perfis de Acesso (RBAC)
define('PERFIL_ADMIN',              'ADMIN');
define('PERFIL_RH',                 'RH');               // Recursos Humanos: Gestão exclusiva de cadastro de usuários
define('PERFIL_PRESIDENTE',         'PRESIDENTE');
define('PERFIL_DIRECAO_SUPERIOR',   'DIRECAO_SUPERIOR'); // Cel, Ten Cel, Maj
define('PERFIL_CHEFE_DIV_ASSESS',   'CHEFE_DIV_ASSESS'); // Chefes DPC, DA, DL, DE, DS e Representação das Assessorias
define('PERFIL_CHEFE_DIRETO',       'CHEFE_DIRETO');     // Oficiais avaliadores de seus subordinados
define('PERFIL_ED_FISICA',          'ED_FISICA');        // Seção de Educação Física / DAEF
define('PERFIL_ELEITOR',            'ELEITOR');          // Todo o efetivo

// Postos exclusivos de Oficiais
const POSTOS_OFICIAIS = [
    'Ten-Brig', 'Maj-Brig', 'Brig',
    'Cel', 'Ten Cel', 'Maj', 'Cap',
    '1º Ten', '1° Ten', '1S Ten', '1 Ten',
    '2º Ten', '2° Ten', '2S Ten', '2 Ten'
];

// Postos que integram o Estado-Maior na Fase 4 (Majores, Tenentes-Coronéis e Coronéis)
const POSTOS_ESTADO_MAIOR = [
    'Cel',
    'Ten Cel',
    'Maj'
];

// Postos habilitados exclusivamente para avaliação na Fase 1 (Tenentes e Capitães)
const POSTOS_AVALIADORES_FASE1 = [
    'Cap',
    '1º Ten', '1° Ten', '1S Ten', '1 Ten',
    '2º Ten', '2° Ten', '2S Ten', '2 Ten',
    'Ten'
];

// Siglas das Assessorias da COMARA que compõem a representação unificada
const SIGLAS_ASSESSORIAS = ['AINT', 'ACI', 'AGOV', 'APOG', 'SCS', 'AJUR', 'SIJ'];

// Divisões e Assessorias com prerrogativa de seleção na Fase 2
// 5 Divisões + 1 Representação Unificada das Assessorias = 6 Selecionadores
const ORGAOS_SELECAO_FASE2 = [
    'DIVISOES' => [
        'DPC' => 'Divisão de Planejamento e Coordenação',
        'DA'  => 'Divisão de Apoio',
        'DL'  => 'Divisão de Logística',
        'DE'  => 'Divisão de Engenharia',
        'DS'  => 'Divisão de Suprimento'
    ],
    'ASSESSORIAS' => [
        'ASSESSORIAS' => 'Representação Unificada das Assessorias'
    ]
];

// Quantidade exata de candidatos por categoria que cada órgão DEVE escolher na Fase 2 (1 de cada divisão + 1 de todas as assessorias = 6 para a Fase 3)
define('QTD_SELECAO_FASE2', 1);
