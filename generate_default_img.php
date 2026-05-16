<?php
// Garantir que a pasta existe
$dir = __DIR__ . '/assets/img/products/';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// Configurar as dimensões da imagem
$width = 400;
$height = 400;

// Criar a imagem
$img = imagecreatetruecolor($width, $height);

// Definir as cores (fundo roxo escuro, texto roxo claro)
$bg = imagecolorallocate($img, 30, 15, 50); // Fundo tipo #1e0f32
$text_color = imagecolorallocate($img, 188, 111, 241); // Cor acento tipo #bc6ff1

// Preencher fundo
imagefill($img, 0, 0, $bg);

// Definir o texto e a fonte
$text = 'TSTORE';
$font = 5; // Fonte nativa do GD (1-5)
$tw = imagefontwidth($font) * strlen($text);
$th = imagefontheight($font);

// Centralizar e adicionar o texto
$x = ($width - $tw) / 2;
$y = ($height - $th) / 2;
imagestring($img, $font, $x, $y, $text, $text_color);

// Salvar a imagem no diretório correto
imagepng($img, $dir . 'default_product.png');

// Limpar memória
imagedestroy($img);
echo "Imagem default_product.png gerada com sucesso em: " . $dir;
?>
