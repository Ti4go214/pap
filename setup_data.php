<?php
include 'includes/db_connect.php';

// 1. Criar Taxa de IVA (se não existir)
$conn->query("INSERT IGNORE INTO iva_taxas (id_taxa, descricao, taxa) VALUES (1, 'Taxa Normal', 23.00)");

// Garantir que a tabela produtos tem a coluna imagem
$res_img = $conn->query("SHOW COLUMNS FROM produtos LIKE 'imagem'");
if ($res_img && $res_img->num_rows === 0) {
    $conn->query("ALTER TABLE produtos ADD COLUMN imagem VARCHAR(255) NULL");
}

// 2. Injetar Categorias Premium
$categorias = [
    ['id_categoria' => 1, 'descricao' => 'Smartphones & Mobile', 'id_iva' => 1],
    ['id_categoria' => 2, 'descricao' => 'Portáteis & Desktops', 'id_iva' => 1],
    ['id_categoria' => 3, 'descricao' => 'Áudio Premium', 'id_iva' => 1],
    ['id_categoria' => 4, 'descricao' => 'Acessórios & Gaming', 'id_iva' => 1]
];

foreach ($categorias as $cat) {
    $stmt = $conn->prepare("INSERT INTO categoria (id_categoria, descricao, id_iva) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descricao=VALUES(descricao)");
    $stmt->bind_param("isi", $cat['id_categoria'], $cat['descricao'], $cat['id_iva']);
    $stmt->execute();
}

// 3. Injetar Produtos Premium com as Imagens Geradas
$produtos = [
    [
        'id_produto' => 1,
        'descricao' => 'Smartphone Quantum X Pro - 512GB (Preto Meia-Noite)',
        'id_categoria' => 1,
        'preco_unit' => 1099.90,
        'quantidade' => 45,
        'stock_minimo' => 10,
        'imagem' => 'uploads/smartphone.png'
    ],
    [
        'id_produto' => 2,
        'descricao' => 'Portátil Ultrabook Aero M3 - 16GB RAM / 1TB SSD',
        'id_categoria' => 2,
        'preco_unit' => 1450.00,
        'quantidade' => 12,
        'stock_minimo' => 5,
        'imagem' => 'uploads/laptop.png'
    ],
    [
        'id_produto' => 3,
        'descricao' => 'Auscultadores Sonic Max ANC - Over-Ear Wireless',
        'id_categoria' => 3,
        'preco_unit' => 349.00,
        'quantidade' => 80,
        'stock_minimo' => 20,
        'imagem' => 'uploads/headphones.png'
    ]
];

foreach ($produtos as $p) {
    // Usar uma consulta mais simples
    $stmt = $conn->prepare("INSERT INTO produtos (descricao, id_categoria, preco_unit, quantidade, imagem) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Erro no prepare: " . $conn->error);
    }
    $stmt->bind_param("sidds", $p['descricao'], $p['id_categoria'], $p['preco_unit'], $p['quantidade'], $p['imagem']);
    $stmt->execute();
}

echo "Dados Premium injetados com sucesso!";
?>
