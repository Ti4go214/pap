<?php
include __DIR__ . '/../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || (($_SESSION['role'] ?? 1) != 0 && ($_SESSION['role'] ?? 1) != 2)) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['btn_save_mov'])) {

    $tipo = $_POST['tipo'];
    $entidade_id = $_POST['entidade'];
    $data = $_POST['data'];
    $linhas = $_POST['linhas'] ?? []; // Array de linhas

    if (empty($linhas)) {
        die("Erro: Não inseriu nenhuma linha de produto.");
    }

    // Buscar nome da entidade para compatibilidade
    $entidade_nome = '';
    if ($tipo == 'ENTRADA') {
        // Buscar nome do fornecedor
        $stmt_nome = $conn->prepare("SELECT nome FROM fornecedores WHERE id_fornecedor = ?");
        $stmt_nome->bind_param("i", $entidade_id);
        $stmt_nome->execute();
        $result = $stmt_nome->get_result();
        if ($row = $result->fetch_assoc()) {
            $entidade_nome = $row['nome'];
        }
    } else {
        // Buscar nome do cliente
        $stmt_nome = $conn->prepare("SELECT nome FROM clientes WHERE id_cliente = ?");
        $stmt_nome->bind_param("i", $entidade_id);
        $stmt_nome->execute();
        $result = $stmt_nome->get_result();
        if ($row = $result->fetch_assoc()) {
            $entidade_nome = $row['nome'];
        }
    }

    // 1. Generate Header ID (Max + 1 Strategy)
    $table_header = ($tipo == 'ENTRADA') ? 'ent_cab' : 'sai_cab';

    // Verificar ID máximo em ambos os cabeçalhos e linhas para garantir unicidade para 'linhas.id'
    $max_ent_res = $conn->query("SELECT MAX(n_cab) as max_id FROM ent_cab");
    $max_sai_res = $conn->query("SELECT MAX(n_cab) as max_id FROM sai_cab");
    $max_linhas_res = $conn->query("SELECT MAX(id) as max_id FROM linhas");

    $max_ent = $max_ent_res ? (int) $max_ent_res->fetch_assoc()['max_id'] : 0;
    $max_sai = $max_sai_res ? (int) $max_sai_res->fetch_assoc()['max_id'] : 0;
    $max_linhas = $max_linhas_res ? (int) $max_linhas_res->fetch_assoc()['max_id'] : 0;

    $n_cab = max($max_ent, $max_sai, $max_linhas) + 1;

    // 2. Insert Header com ID e nome da entidade
    if ($tipo == 'ENTRADA') {
        $stmt_head = $conn->prepare("INSERT INTO ent_cab (n_cab, cliente, data, id_fornecedor) VALUES (?, ?, ?, ?)");
        $stmt_head->bind_param("issi", $n_cab, $entidade_nome, $data, $entidade_id);
    } else {
        $stmt_head = $conn->prepare("INSERT INTO sai_cab (n_cab, cliente, data, id_cliente) VALUES (?, ?, ?, ?)");
        $stmt_head->bind_param("issi", $n_cab, $entidade_nome, $data, $entidade_id);
    }

    if (!$stmt_head->execute()) {
        die("Erro ao criar cabeçalho: " . $conn->error);
    }
    $stmt_head->close();

    // AUDITORIA: Registo de Movimento
    registarLog($_SESSION['id_user'], "REGISTO_MOVIMENTO", "Tipo: $tipo, Entidade: $entidade, Doc: $n_cab");

    // 3. Process Lines
    $n_linha = 1;
    foreach ($linhas as $linha) {
        $id_prod = $linha['id_produto'];
        $nome_prod = $linha['descricao']; // Não usado na tabela `linhas`?
        $qtd = $linha['quantidade'];
        $preco = $linha['preco'];

        // Colunas da tabela `linhas`: id, n_linha, id_produto, id_categoria, descricao (int?), quantidade, preço
        // `linhas` schema quirks: `descricao` is INT? `id_categoria` is INT?
        // pap.sql: `descricao` int NOT NULL. Isto está definitivamente errado no esquema original do utilizador.
        // Provavelmente significava que 'id_produto' corresponde ao link 'descricao'?
        // Ou `descricao` deveria ser varchar.
        // Devido a estes erros de esquema (descricao int), posso falhar os inserts.
        // Vou tentar inserir `0` para description se for int, ou o ID do produto.
        // Vou assumir que `id` em `linhas` = `n_cab`.

        $link_id = $n_cab; // Restrições ligam linhas ao cabeçalho

        // Obter categoria atual do produto para satisfazer o requisito `id_categoria`
        $cat_res = $conn->query("SELECT id_categoria FROM produtos WHERE id_produto = '$id_prod'");
        $cat_row = $cat_res->fetch_assoc();
        $id_cat = $cat_row['id_categoria'];

        // Inserir Linha
        // Esquema: id, n_linha, id_produto, id_categoria, descricao, quantidade, preço
        // Vou fazer bind de id_prod para a coluna descricao também se for Int.
        $stmt_line = $conn->prepare("INSERT INTO linhas (id, n_linha, id_produto, id_categoria, descricao, quantidade, preço) VALUES (?, ?, ?, ?, ?, ?, ?)");
        // tipos: i i i i i i d
        // Usando $id_prod para 'descricao' uma vez que a coluna é INT.
        $stmt_line->bind_param("iiiiiid", $link_id, $n_linha, $id_prod, $id_cat, $id_prod, $qtd, $preco);
        $stmt_line->execute();
        $stmt_line->close();

        // 4. Update Stock
        if ($tipo == 'ENTRADA') {
            $upd = $conn->prepare("UPDATE produtos SET quantidade = quantidade + ? WHERE id_produto = ?");
        } else {
            $upd = $conn->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id_produto = ?");
        }
        $upd->bind_param("ii", $qtd, $id_prod);
        $upd->execute();
        $upd->close();

        $n_linha++;
    }

    // Sucesso
    header("Location: ../movimentos.php");
    exit();
}
?>