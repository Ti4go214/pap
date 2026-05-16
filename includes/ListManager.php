<?php
class ListManager
{
    private $conn;
    private $limit;
    private $page;
    public $offset;
    public $filterValue = "";
    public $totalRecords = 0;
    public $totalPages = 1;
    public $paramKey = 'filtro_username';
    public $pageKey = 'pagina';

    public function __construct($conn, $limit = 6)
    {
        $this->conn = $conn;
        $this->limit = $limit;

        // Paginação
        $this->page = isset($_GET[$this->pageKey]) ? (int) $_GET[$this->pageKey] : 1;
        if ($this->page < 1)
            $this->page = 1;
        $this->offset = ($this->page - 1) * $this->limit;

        // Filtros
        if (isset($_GET[$this->paramKey]) && !empty(trim($_GET[$this->paramKey]))) {
            $this->filterValue = trim($_GET[$this->paramKey]);
        }
    }

    // Gera a parte SQL WHERE para uma pesquisa LIKE
    public function getFilterSQL($columns, $prefix = "")
    {
        if (!empty($this->filterValue)) {
            $value = $this->conn->real_escape_string($this->filterValue);

            if (is_array($columns)) {
                $conditions = [];
                foreach ($columns as $col) {
                    $c = $prefix ? "$prefix.$col" : $col;
                    $conditions[] = "$c LIKE '%$value%'";
                }
                return "WHERE (" . implode(" OR ", $conditions) . ")";
            } else {
                $col = $prefix ? "$prefix.$columns" : $columns;
                $col = trim($col);
                return "WHERE $col LIKE '%$value%'";
            }
        }
        return "";
    }

    // Calcula totais
    public function calculatePagination($table, $filterSQL)
    {
        $total_sql = "SELECT COUNT(*) As total FROM $table $filterSQL";
        $total_result = $this->conn->query($total_sql);
        if ($total_result) {
            $total_row = $total_result->fetch_assoc();
            $this->totalRecords = $total_row['total'];
            $this->totalPages = ceil($this->totalRecords / $this->limit);
            if ($this->totalPages < 1)
                $this->totalPages = 1;
        } else {
            // Fallback se a query falhar (pode ajudar na depuração se necessário)
            $this->totalRecords = 0;
            $this->totalPages = 1;
        }
    }

    // Renderiza o HTML da Barra de Pesquisa
    public function renderSearchBar($placeholder = "Pesquisar...")
    {
        $val = htmlspecialchars($this->filterValue);
        echo "
        <div>
            <form method='GET' action='' >
                <input type='text' name='{$this->paramKey}' placeholder='$placeholder' value='$val' 
                       style='background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 12px 15px; border-radius: 10px; color: #fff; width: 100%;'>
            </form>
        </div>";
    }

    // Renderiza o HTML dos Links de Paginação
    public function renderPagination()
    {
        if ($this->totalPages <= 1)
            return;

        $filterUrl = urlencode($this->filterValue);
        echo '<div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center; align-items: center;">';

        // Botão Anterior
        if ($this->page > 1) {
            $prevPage = $this->page - 1;
            echo "<a href='?{$this->pageKey}=$prevPage&{$this->paramKey}=$filterUrl' 
                       style='padding: 8px 12px; border-radius: 5px; text-decoration: none; 
                              background: rgba(255,255,255,0.1); 
                              color: #fff;'><i class='fas fa-chevron-left'></i> Anterior</a>";
        }

        // Paginadores
        for ($i = 1; $i <= $this->totalPages; $i++) {
            $activeStyle = ($i == $this->page) ? '#bc6ff1' : 'rgba(255,255,255,0.1)';
            echo "<a href='?{$this->pageKey}=$i&{$this->paramKey}=$filterUrl' 
                       style='padding: 8px 12px; border-radius: 5px; text-decoration: none; 
                              background: $activeStyle; 
                              color: #fff;'>$i</a>";
        }

        // Botão Seguinte
        if ($this->page < $this->totalPages) {
            $nextPage = $this->page + 1;
            echo "<a href='?{$this->pageKey}=$nextPage&{$this->paramKey}=$filterUrl' 
                       style='padding: 8px 12px; border-radius: 5px; text-decoration: none; 
                              background: rgba(255,255,255,0.1); 
                              color: #fff;'>Seguinte <i class='fas fa-chevron-right'></i></a>";
        }

        echo '</div>';
    }
}
?>