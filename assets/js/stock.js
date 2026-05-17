function abrirModalStock() {
    document.getElementById("modalStock").style.display = "flex";
    document.getElementById("modalTitleStock").innerText = "Novo Produto";

    // Reset 
    document.getElementById("s_id").value = "";
    document.getElementById("s_descricao").value = "";
    document.getElementById("s_quantidade").value = "";
    document.getElementById("s_preco").value = "";
    document.getElementById("s_imagem").value = "";
    document.getElementById("imagePreview").innerHTML = '<i class="fas fa-image" style="opacity: 0.3;"></i>';
}

function fecharModalStock() {
    document.getElementById("modalStock").style.display = "none";
}

function editarModalStock(id, descricao, id_categoria, quantidade, preco, imagem) {
    document.getElementById("modalStock").style.display = "flex";
    document.getElementById("modalTitleStock").innerText = "Editar Produto #" + id;

    document.getElementById("s_id").value = id;
    document.getElementById("s_descricao").value = descricao;
    document.getElementById("s_categoria").value = id_categoria;
    document.getElementById("s_quantidade").value = quantidade;
    document.getElementById("s_preco").value = preco;
    document.getElementById("s_imagem").value = ""; // Reset file input

    // Preview current image
    const preview = document.getElementById("imagePreview");
    if (imagem && imagem !== 'default_product.png') {
        preview.innerHTML = `<img src="assets/img/products/${imagem}" style="width:100%; height:100%; object-fit:cover;">`;
    } else {
        preview.innerHTML = '<i class="fas fa-image" style="opacity: 0.3;"></i>';
    }
}

function eliminarStock(id, descricao) {
    showConfirm("Tem a certeza que deseja eliminar o produto '" + descricao + "'?", () => {
        window.location.href = "actions/stock_actions.php?delete=" + id;
    }, "Eliminar Produto", "fa-trash-alt");
}


window.onclick = function (event) {
    let modal = document.getElementById("modalStock");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
