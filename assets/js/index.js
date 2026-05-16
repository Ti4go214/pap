function abrirModal() {
    document.getElementById("modalUser").style.display = "flex";
    document.getElementById("modalTitle").innerText = "Novo Utilizador";
    document.getElementById("f_user").value = "";
    document.getElementById("f_user").readOnly = false;
    document.getElementById("f_pwd").value = "";
    document.getElementById("f_pwd").placeholder = "";
    document.getElementById("f_nome").value = "";
    document.getElementById("f_morada").value = "";
    document.getElementById("f_postal").value = "";
    document.getElementById("f_nascimento").value = "";
    document.getElementById("f_nif").value = "";
    document.getElementById("f_contacto").value = "";
    document.getElementById("f_pais").value = "";
    document.getElementById("f_role").value = "1";
}

function fecharModal() {
    document.getElementById("modalUser").style.display = "none";
}

function editarModal(button) {
    console.log('editarModal chamado', button);
    console.log('Dataset:', button.dataset);
    
    document.getElementById("modalUser").style.display = "flex";
    document.getElementById("modalTitle").innerText = "Editar Utilizador";
    
    // Ler dados dos data-attributes usando dataset
    const username = button.dataset.username;
    const role = button.dataset.role;
    const nome = button.dataset.nome || "";
    const morada = button.dataset.morada || "";
    const postal = button.dataset.postal || "";
    const nascimento = button.dataset.nascimento || "";
    const nif = button.dataset.nif || "";
    const contacto = button.dataset.contacto || "";
    const pais = button.dataset.pais || "";
    
    console.log('Dados lidos:', { username, role, nome, morada, postal, nascimento, nif, contacto, pais });
    
    let userField = document.getElementById("f_user");
    userField.value = username;
    userField.readOnly = true; // Don't change username usually
    
    document.getElementById("f_pwd").placeholder = "(Deixe vazio para manter)";
    
    // Preencher todos os campos
    document.getElementById("f_nome").value = nome;
    document.getElementById("f_morada").value = morada;
    document.getElementById("f_postal").value = postal;
    document.getElementById("f_nascimento").value = nascimento;
    document.getElementById("f_nif").value = nif;
    document.getElementById("f_contacto").value = contacto;
    document.getElementById("f_pais").value = pais;
    document.getElementById("f_role").value = role;
    
    console.log('Campos preenchidos');
}

function eliminar(username) {
    if(confirm("Tem a certeza que deseja eliminar o utilizador " + username + "?")) {
        window.location.href = "actions/processar.php?delete=" + username;
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    let modal = document.getElementById("modalUser");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
