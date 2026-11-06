<?php
declare(strict_types=1);

/** Retorna apenas a ampliação necessária, preservando os valores e atributos existentes. */
function correcaoPerfilRh(PDO $pdo): ?array
{
    $c=$pdo->query("SHOW FULL COLUMNS FROM usuarios LIKE 'perfil'")->fetch();
    if (!$c) throw new RuntimeException('Coluna usuarios.perfil ausente. Solicite conferência ao STI.');
    $tipo=$c['Type'];
    if (preg_match('/^(?:var)?char\((\d+)\)$/i',$tipo,$m) && (int)$m[1]>=2) return null;
    if (!preg_match('/^enum\(/i',$tipo)) throw new RuntimeException('Tipo de usuarios.perfil não reconhecido. Solicite conferência ao STI.');
    // O tipo vem do próprio servidor, não da requisição. Não reordenar/remover membros do ENUM.
    if (preg_match("/(?:\\(|,)'RH'(?:,|\\))/",$tipo)) return null;
    $novo=substr($tipo,0,-1).",'RH')";
    $sql='ALTER TABLE usuarios MODIFY COLUMN perfil '.$novo;
    if ($c['Collation']) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/',$c['Collation'])) throw new RuntimeException('Collation não reconhecida.');
        $sql.=' COLLATE '.$c['Collation'];
    }
    $sql.=$c['Null']==='YES'?' NULL':' NOT NULL';
    if ($c['Default']!==null) $sql.=' DEFAULT '.$pdo->quote($c['Default']);
    elseif ($c['Null']==='YES') $sql.=' DEFAULT NULL';
    $sql.=' COMMENT '.$pdo->quote($c['Comment']);
    return ['descricao'=>'Adicionar RH aos perfis aceitos em usuarios.perfil','sql'=>$sql];
}
