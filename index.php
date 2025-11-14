<?php
/* --- LÓGICA DE PHP CON PAGINACIÓN Y ORDENAMIENTO --- */

$show_results = isset($_GET['consultar']);
$resultados = [];
$total_registros = 0;
$total_paginas = 0;

// --- LÓGICA DE PAGINACIÓN ---
$limit = 500; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

// --- LÓGICA DE ORDENAMIENTO ---
// 1. Lista blanca de columnas seguras para ordenar
$allowed_sort_cols = [
    'FECDIA', 'HORTRANSACCION', 'CODAGENTEVIABCP', 'TIPTRANSACCIONAGENTEVIABCP',
    'NUMTRANSACCION', 'MTOTRANSACCION', 'FLGIMPRESIONCOMPROBANTE', 'TIPESTTRANSACCIONAGENTEVIABCP'
];

// 2. Valores por defecto
$sort_by_default = 'FECDIA';
$order_by_default = 'ASC';

// 3. Obtener valores de la URL (o usar los por defecto)
$sort_by = $_GET['sort_by'] ?? $sort_by_default;
$order = strtoupper($_GET['order'] ?? $order_by_default);

// 4. Validar los valores (seguridad)
if (!in_array($sort_by, $allowed_sort_cols)) {
    $sort_by = $sort_by_default;
}
if ($order !== 'ASC' && $order !== 'DESC') {
    $order = $order_by_default;
}

// 5. Preparar la consulta SQL
$sql_order_by = " ORDER BY $sort_by $order, FECDIA ASC, HORTRANSACCION ASC";

// --- LÓGICA DE FILTROS (Formulario) ---
$terminal = $_GET['terminal'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';


if ($show_results) {
    
    require_once 'db.php'; 

    $where_sql = " WHERE 1=1";
    $params_types = "";  
    $params_values = []; 

    if (!empty($terminal)) {
        $where_sql .= " AND CODAGENTEVIABCP = ?";
        $params_types .= "s";
        $params_values[] = $terminal;
    }
    if (!empty($fecha_inicio)) {
        $where_sql .= " AND FECDIA >= ?";
        $params_types .= "s";
        $params_values[] = $fecha_inicio;
    }
    if (!empty($fecha_fin)) {
        $where_sql .= " AND FECDIA <= ?";
        $params_types .= "s";
        $params_values[] = $fecha_fin;
    }

    // --- 2. OBTENER EL TOTAL DE REGISTROS ---
    $sql_count = "SELECT COUNT(*) as total FROM pos_servicios" . $where_sql;
    
    if ($stmt_count = $conn->prepare($sql_count)) {
        if (!empty($params_types)) {
            $stmt_count->bind_param($params_types, ...$params_values);
        }
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $total_registros = $result_count->fetch_assoc()['total'];
        $total_paginas = ceil($total_registros / $limit);
        $stmt_count->close();
    } else {
        echo "Error al preparar el conteo: " . $conn->error;
    }

    
    // --- 3. OBTENER LOS REGISTROS DE LA PÁGINA (CON ORDEN) ---
    if ($total_registros > 0) {
        $sql_data = "SELECT 
                        FECDIA, HORTRANSACCION, CODAGENTEVIABCP, TIPTRANSACCIONAGENTEVIABCP,
                        NUMTRANSACCION, CODOPECTACARGO, CODOPECTAABONO, MTOTRANSACCION,
                        FLGIMPRESIONCOMPROBANTE, TIPESTTRANSACCIONAGENTEVIABCP, TIPINDICADORPINAGENTE
                     FROM pos_servicios" 
                     . $where_sql
                     . $sql_order_by  // <-- ¡Se usa la variable de orden!
                     . " LIMIT ? OFFSET ?";

        $params_types_data = $params_types . "ii"; 
        $params_values_data = [...$params_values, $limit, $offset];

        if ($stmt_data = $conn->prepare($sql_data)) {
            $stmt_data->bind_param($params_types_data, ...$params_values_data);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) {
                $resultados[] = $row;
            }
            $stmt_data->close();
        } else {
            echo "Error al preparar la consulta de datos: " . $conn->error;
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema POS</title>
    
    <style>
        /* --- ESTILOS (Sin cambios) --- */
        :root {
            --bcp-blue: #003882;
            --bcp-blue-dark: #002a64;
            --neutral-gray: #6c757d;
            --light-gray: #f4f6f9; 
            --white: #ffffff;
            --border-color: #dddfe2;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: var(--light-gray); 
        }
        .container {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
            max-width: 95%; 
            margin: 20px auto;
        }
        h1 {
            text-align: center;
            color: var(--bcp-blue); 
            margin-top: 0;
        }
        form {
            display: flex;
            flex-wrap: wrap; 
            gap: 1rem;
            margin-bottom: 2rem;
            align-items: flex-end; 
        }
        .form-group {
            flex: 1 1 200px; /* Base 200px */
            min-width: 200px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #4b4f56;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box; 
            transition: all 0.2s;
        }
        .form-group input:focus {
            border-color: var(--bcp-blue);
            box-shadow: 0 0 0 3px rgba(0, 56, 130, 0.2);
            outline: none;
        }
        .form-buttons-group {
            display: flex;
            gap: 1rem;
            flex: 1; 
            min-width: 240px; 
        }
        .form-button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            background-color: var(--bcp-blue); 
            color: var(--white);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
            flex-grow: 1; 
            text-align: center;
        }
        .form-button:hover {
            background-color: var(--bcp-blue-dark); 
        }
        .clear-button {
            background-color: var(--neutral-gray);
        }
        .clear-button:hover {
            background-color: #5a6268;
        }
        .results-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap; 
            gap: 1rem;
        }
        .total-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        .total-display #visibleCountDisplay {
            font-weight: 400;
            font-size: 0.95rem;
            margin-left: 1rem;
            color: #555;
            display: none; 
        }
        .quick-filter {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            width: 300px; 
        }
        .quick-filter:focus {
            border-color: var(--bcp-blue);
            box-shadow: 0 0 0 3px rgba(0, 56, 130, 0.2);
            outline: none;
        }
        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center; 
            margin-top: 2rem; 
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--bcp-blue);
            font-weight: 600;
        }
        .pagination a {
            border: 1px solid var(--border-color);
            background-color: var(--white);
        }
        .pagination a:hover {
            background-color: #f0f2f5;
        }
        .pagination span.current {
            background-color: var(--bcp-blue);
            color: var(--white);
            border: 1px solid var(--bcp-blue);
        }
        .pagination span.disabled {
            color: #aaa;
            border: 1px solid #eee;
        }
        .results-container {
            margin-top: 1rem;
            overflow-x: auto; 
        }
        table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid var(--border-color);
            padding: 12px;
            white-space: nowrap; 
        }
        th {
            background-color: var(--bcp-blue);
            color: var(--white);
            font-weight: 600;
            position: sticky; 
            top: 0;
            text-align: center;      
            vertical-align: middle; 
        }
        
        /* --- NUEVO CSS PARA LOS ENLACES DE CABECERA --- */
        th a {
            color: var(--white); /* Hereda el color blanco */
            text-decoration: none; /* Sin subrayado */
        }
        th a:hover {
            text-decoration: underline; /* Subrayado solo al pasar el mouse */
        }
        .sort-arrow {
            font-size: 0.8em;
            margin-left: 4px;
        }
        /* --- FIN NUEVO CSS --- */
        
        td {
            text-align: left;
            vertical-align: middle;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .no-results {
            text-align: center;
            color: #777;
            padding: 2rem;
            border: 1px dashed var(--border-color);
            border-radius: 6px;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <h1>Sistema POS</h1>
        
        <form action="index.php" method="GET">
            
            <div class="form-group">
                <label for="terminal">Terminal:</label>
                <input type="text" id="terminal" name="terminal" placeholder="Ej: H999830" 
                       value="<?php echo htmlspecialchars($terminal); ?>">
            </div>
            
            <div class="form-group">
                <label for="fecha_inicio">Fecha de Inicio:</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio"
                       value="<?php echo htmlspecialchars($fecha_inicio); ?>">
            </div>

            <div class="form-group">
                <label for="fecha_fin">Fecha de Fin:</label>
                <input type="date" id="fecha_fin" name="fecha_fin"
                       value="<?php echo htmlspecialchars($fecha_fin); ?>">
            </div>

            <div class="form-buttons-group">
                <button type="submit" class="form-button" name="consultar" value="1">Consultar</button>
                <a href="index.php" class="form-button clear-button">Limpiar</a>
            </div>
            
        </form>

        
        <?php if ($show_results): ?>
            <div class="results-toolbar">
                <div class="total-display">
                    <strong><?php echo number_format($total_registros); ?></strong> registros encontrados.
                    <span id="visibleCountDisplay">
                        (Mostrando <span id="visibleCount"></span> en esta página)
                    </span>
                </div>

                <input type="text" id="quickFilterInput" class="quick-filter" 
                       placeholder="Filtrar N° Op. o Monto en esta página...">
            </div>
        <?php endif; ?>
        

        
        <div class="results-container">
            <?php if ($show_results && !empty($resultados)): ?>
                
                <?php
                // --- LÓGICA DE ENLACES DE ORDENAMIENTO ---
                // 1. Preparamos la URL base (mantiene todos los filtros GET actuales)
                $query_params = $_GET;
                unset($query_params['page']); // El ordenamiento siempre te lleva a la pág 1
                
                // 2. Función para crear el enlace de cabecera
                function sort_link($col_name, $label, $current_sort, $current_order, $query_params) {
                    $next_order = ($current_sort == $col_name && $current_order == 'ASC') ? 'DESC' : 'ASC';
                    $arrow = '';
                    if ($current_sort == $col_name) {
                        $arrow = ($current_order == 'ASC') ? ' <span class="sort-arrow">🔼</span>' : ' <span class="sort-arrow">🔽</span>';
                    }
                    
                    $query_params['sort_by'] = $col_name;
                    $query_params['order'] = $next_order;
                    
                    $url = "index.php?" . http_build_query($query_params);
                    
                    return '<th><a href="' . $url . '">' . $label . $arrow . '</a></th>';
                }
                ?>
            
                <table id="resultsTable">
                    <thead>
                        <tr>
                            <?php echo sort_link('FECDIA', 'Fecha', $sort_by, $order, $query_params); ?>
                            <?php echo sort_link('HORTRANSACCION', 'Hora', $sort_by, $order, $query_params); ?>
                            <?php echo sort_link('CODAGENTEVIABCP', 'Terminal', $sort_by, $order, $query_params); ?>
                            <?php echo sort_link('TIPTRANSACCIONAGENTEVIABCP', 'Tipo<br>Transacción', $sort_by, $order, $query_params); ?>
                            <?php echo sort_link('NUMTRANSACCION', 'N°<br>Operación', $sort_by, $order, $query_params); ?>
                            <th>Cta.<br>Cargo</th> <th>Cta.<br>Abono</th> <?php echo sort_link('MTOTRANSACCION', 'Monto', $sort_by, $order, $query_params); ?>
                            <?php echo sort_link('FLGIMPRESIONCOMPROBANTE', 'Impresión<br>de Voucher', $sort_by, $order, $query_params); ?>
                            <?php echo sort_link('TIPESTTRANSACCIONAGENTEVIABCP', 'Estado<br>Transacción', $sort_by, $order, $query_params); ?>
                            <th>Pin<br>Agente</th> </tr>
                    </thead>
                    
                    <tbody id="resultsBody">
                        <?php foreach ($resultados as $fila): ?>
                            <tr>
                                <td class="text-center"><?php echo date("d/m/Y", strtotime($fila['FECDIA'])); ?></td>
                                <td class="text-center">
                                    <?php 
                                        $hora_texto = $fila['HORTRANSACCION'];
                                        echo substr($hora_texto, 0, 2) . ':' . 
                                             substr($hora_texto, 2, 2) . ':' . 
                                             substr($hora_texto, 4, 2);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($fila['CODAGENTEVIABCP']); ?></td>
                                <td><?php echo htmlspecialchars($fila['TIPTRANSACCIONAGENTEVIABCP']); ?></td>
                                <td class="text-center"><?php echo str_pad(htmlspecialchars($fila['NUMTRANSACCION']), 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($fila['CODOPECTACARGO']); ?></td>
                                <td><?php echo htmlspecialchars($fila['CODOPECTAABONO']); ?></td>
                                <td class="text-right">S/ <?php echo number_format($fila['MTOTRANSACCION'], 2, ',', '.'); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($fila['FLGIMPRESIONCOMPROBANTE']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($fila['TIPESTTRANSACCIONAGENTEVIABCP']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($fila['TIPINDICADORPINAGENTE']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($show_results && empty($resultados)): ?>
                <p class="no-results">No se encontraron resultados para los criterios seleccionados.</p>
                
            <?php endif; ?>
        </div> 

        <?php if ($show_results && $total_paginas > 1): ?>
            <nav class="pagination">
                <?php
                // Preparamos la URL base para paginación (AHORA INCLUYE LOS PARÁMETROS DE ORDEN)
                $query_params = $_GET;
                unset($query_params['page']);
                $base_url = "index.php?" . http_build_query($query_params) . "&";
                ?>

                <?php if ($page > 1): ?>
                    <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>">« Anterior</a>
                <?php else: ?>
                    <span class="disabled">« Anterior</span>
                <?php endif; ?>

                <span class="current">
                    Página <?php echo $page; ?> de <?php echo $total_paginas; ?>
                </span>

                <?php if ($page < $total_paginas): ?>
                    <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>">Siguiente »</a>
                <?php else: ?>
                    <span class="disabled">Siguiente »</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </div> 

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            const filterInput = document.getElementById("quickFilterInput");
            const tableBody = document.getElementById("resultsBody");
            const visibleCountSpan = document.getElementById("visibleCount");
            const visibleCountDisplay = document.getElementById("visibleCountDisplay");
            
            if (filterInput && tableBody) {
                
                filterInput.addEventListener("keyup", function() {
                    
                    const filterText = filterInput.value.toLowerCase();
                    const rows = tableBody.getElementsByTagName("tr");
                    let visibleCount = 0; 
                    
                    for (let row of rows) {
                        const numOpCell = row.cells[4].textContent || row.cells[4].innerText;
                        const montoCell = row.cells[7].textContent || row.cells[7].innerText;
                        
                        if (numOpCell.toLowerCase().indexOf(filterText) > -1 || 
                            montoCell.toLowerCase().indexOf(filterText) > -1) 
                        {
                            row.style.display = "";
                            visibleCount++; 
                        } else {
                            row.style.display = "none";
                        }
                    }
                    
                    visibleCountSpan.textContent = visibleCount;
                    if (filterText.length > 0) {
                        visibleCountDisplay.style.display = "inline";
                    } else {
                        visibleCountDisplay.style.display = "none";
                    }
                });
            }
        });
    </script>

</body>
</html>
