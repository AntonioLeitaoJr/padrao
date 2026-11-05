<?php
declare(strict_types=1);
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Models/Pleito.php';
require_once __DIR__ . '/../Models/EstadoMaior.php';

class Fase4Controller {
    public function index(): void {
        AuthMiddleware::handle();
        if (!AuthManager::isEstadoMaior()) { http_response_code(403); exit('Acesso restrito ao Estado-Maior.'); }
        $user=AuthManager::user(); $pleito=Pleito::getAtivo();
        $membroSelecionado=false;
        $erro=null; $ciclo=null; $situacao=[]; $candidatos=[]; $avaliacao=[]; $progresso=[]; $podeConfirmar=false; $confirmado=false;
        try {
            if (!$pleito) throw new RuntimeException('Nenhum pleito disponível.');
            $p=(int)$pleito['id']; $u=(int)$user['id'];
            $ciclo=EstadoMaior::ciclo($p);
            if (!$ciclo) throw new RuntimeException('Aguarde o encerramento da Fase 3 pela Administração. Pleito antigo já na Fase 4 deve retornar à Fase 3 antes de preparar o novo fluxo.');
            $situacao=EstadoMaior::situacao($p);
            if ($ciclo['aberto_em']) {
                $candidatos=EstadoMaior::candidatos($p);
                $avaliacao=EstadoMaior::avaliacao($p,$u);
                $progresso=EstadoMaior::progresso($p);
                foreach ($progresso as $a) if ((int)$a['usuario_id']===$u) {
                    $membroSelecionado=true;
                    $confirmado=!empty($a['confirmado_em']);
                    $podeConfirmar=!$confirmado && (int)$pleito['fase_atual']===4 && $pleito['status']==='em_andamento'
                        && in_array($user['posto']??'',POSTOS_ESTADO_MAIOR,true);
                }
            }
        } catch (Throwable $e) {
            error_log('[Fase 4] '.$e->getMessage());
            $erro=$e instanceof RuntimeException && !($e instanceof PDOException)
                ? $e->getMessage()
                : 'Não foi possível carregar a Fase 4. Solicite à STI a conferência do banco e do log de erros.';
        }
        // Mesmo desmarcado, o autor pode consultar sua confirmação preservada.
        if ($pleito && !$erro && $avaliacao) $confirmado=true;
        $rascunho=$_SESSION['fase4_rascunho'][(int)($pleito['id']??0)]??[];
        require ROOT_PATH.'/views/fase4/index.php';
    }
    public function confirmar(): void {
        $this->executar(function(int $p,int $u): string {
            if (($_POST['confirmacao']??'')!=='1') throw new RuntimeException('Confirme expressamente a avaliação.');
            $itens=$_POST['categorias']??[];
            if (!is_array($itens)) throw new InvalidArgumentException('Ordenações inválidas.');
            $_SESSION['fase4_rascunho'][$p]=$itens;
            EstadoMaior::confirmar($p,$u,$itens);
            unset($_SESSION['fase4_rascunho'][$p]);
            return 'Ordenações e justificativas gravadas. Confirmação definitiva: não podem mais ser alteradas.';
        });
    }
    public function decidirCorte(): void {
        $this->executar(function(int $p,int $u): string {
            if (($_POST['confirmacao']??'')!=='1') throw new RuntimeException('Confirme expressamente a decisão.');
            $ids=$_POST['candidatos']??[];
            if (!is_array($ids)) throw new InvalidArgumentException('Escolha inválida.');
            EstadoMaior::decidirCorte($p,(string)($_POST['categoria']??''),$ids,(string)($_POST['justificativa']??''),$u);
            return 'Decisão fundamentada registrada. A Administração poderá avançar após resolver todos os cortes pendentes.';
        });
    }
    private function executar(callable $acao): void {
        AuthMiddleware::handle();
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Requisição ou token de segurança inválido.');
            $p=Pleito::getAtivo(); if (!$p) throw new RuntimeException('Nenhum pleito disponível.');
            $msg=$acao((int)$p['id'],(int)AuthManager::user()['id']);
            flash_message('sucesso',$msg,'success');
        } catch (Throwable $e) {
            error_log('[Fase 4 / operação] '.$e->getMessage());
            $mensagem=($e instanceof RuntimeException || $e instanceof InvalidArgumentException) && !($e instanceof PDOException)
                ? $e->getMessage()
                : 'Não foi possível concluir a operação da Fase 4. Solicite à STI a conferência do banco e do log de erros.';
            flash_message('erro',$mensagem,'danger');
        }
        header('Location: /index.php?r=fase4'); exit;
    }
}
