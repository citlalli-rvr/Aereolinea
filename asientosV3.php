
<?php
// asientos.php - Selector de asientos mejorado
require_once 'config.php';
session_start();

function esc($s){ 
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

// Verificar que hay pasajeros en sesión
if (!isset($_SESSION['reserva_temp']) || empty($_SESSION['reserva_temp']['pasajeros'])) {
    header("Location: registro.php");
    exit;
}

$id_vuelo = $_SESSION['reserva_temp']['id_vuelo'];
$pasajeros = $_SESSION['reserva_temp']['pasajeros'];

try {
    // Obtener información del vuelo
    $stmt = $pdo->prepare("SELECT * FROM vuelos WHERE id_vuelo = ?");
    $stmt->execute([$id_vuelo]);
    $vuelo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vuelo) die("Vuelo no encontrado.");

    // Obtener todos los asientos de la tabla de platilla de asientos
    $sql = "SELECT id_detalle, fila, columna, CONCAT(fila, columna) AS codigo 
            FROM plantilla_asiento_detalle 
            ORDER BY fila, columna";
    $stmt = $pdo->query($sql);
    $allSeats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener asientos ya reservados para este vuelo
    $sql = "SELECT va.numero_asiento 
            FROM vuelo_asientos va 
            WHERE va.id_vuelo = ? AND va.disponible = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_vuelo]);
    $reserved_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Construir mapa de asientos
    $seat_rows = [];
    foreach ($allSeats as $seat) {
        $row = intval($seat['fila']);
        $col = $seat['columna'];
        $codigo = $seat['codigo'];
        
        if (!isset($seat_rows[$row])) {
            $seat_rows[$row] = [
                'class' => "Económica",
                'price' => floatval($vuelo['precio']),
                'seats' => []
            ];
        }
        
        // Marcar como ocupado si ya está reservado
        $seat_rows[$row]['seats'][$col] = in_array($codigo, $reserved_seats) ? 'X' : 'A';
    }

    //  PROCESAR RESERVA
    $submitted = false;
    $errors = [];
    $folio_generado = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_submit'])) {
        $selected_seats = $_POST['selected_seats'] ?? [];
        $pass_for_seat = $_POST['passenger_for_seat'] ?? [];

        // Validaciones
        if (count($selected_seats) != count($pasajeros)) {
            $errors[] = "Debes seleccionar exactamente " . count($pasajeros) . " asientos (uno por pasajero).";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                //  Generar folio único
                $folio = 'CH' . strtoupper(substr(md5(uniqid()), 0, 8));
                $contacto = $pasajeros[0]; // El primer pasajero es el contacto

                //  Insertar reserva
                $stmt = $pdo->prepare("INSERT INTO reservas 
                    (folio, nombre_contacto, correo_contacto, telefono_contacto, id_vuelo_ida) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $folio,
                    $contacto['nombre'] . ' ' . $contacto['apellidos'],
                    $contacto['correo'],
                    $contacto['telefono'],
                    $id_vuelo
                ]);
                $id_reserva = $pdo->lastInsertId();

                //  Procesar cada asiento seleccionado
                foreach ($selected_seats as $seat_code) {
                    $pidx = intval($pass_for_seat[$seat_code]);
                    $pasajero = $pasajeros[$pidx - 1];
                    
                    // 1. Obtener id_detalle del asiento
                    $stmt = $pdo->prepare("SELECT id_detalle FROM plantilla_asiento_detalle 
                                          WHERE CONCAT(fila, columna) = ?");
                    $stmt->execute([$seat_code]);
                    $id_detalle = $stmt->fetchColumn();
                    
                    if (!$id_detalle) {
                        throw new Exception("Asiento no válido: $seat_code");
                    }

                    // 2. Insertar o actualizar en vuelo_asientos
                    $stmt = $pdo->prepare("INSERT INTO vuelo_asientos 
                        (id_vuelo, id_detalle, numero_asiento, disponible) 
                        VALUES (?, ?, ?, 0)
                        ON DUPLICATE KEY UPDATE disponible = 0");
                    $stmt->execute([$id_vuelo, $id_detalle, $seat_code]);
                    $id_vuelo_asiento = $pdo->lastInsertId();
                    
                    // Si es UPDATE, obtener el ID existente
                    if ($id_vuelo_asiento == 0) {
                        $stmt = $pdo->prepare("SELECT id_vuelo_asiento FROM vuelo_asientos 
                                              WHERE id_vuelo = ? AND numero_asiento = ?");
                        $stmt->execute([$id_vuelo, $seat_code]);
                        $id_vuelo_asiento = $stmt->fetchColumn();
                    }

                    // 3. Insertar pasajero
                    $stmt = $pdo->prepare("INSERT INTO pasajeros 
                        (id_reserva, id_categoria, nombre, apellidos, asiento_seleccionado) 
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $id_reserva,
                        $pasajero['categoria'],
                        $pasajero['nombre'],
                        $pasajero['apellidos'],
                        $seat_code
                    ]);
                    $id_pasajero = $pdo->lastInsertId();

                    // 4. Asignar asiento al pasajero
                    $stmt = $pdo->prepare("INSERT INTO asignacion_asiento 
                        (id_vuelo_asiento, id_reserva, id_pasajero) 
                        VALUES (?, ?, ?)");
                    $stmt->execute([$id_vuelo_asiento, $id_reserva, $id_pasajero]);
                }

                $pdo->commit();
                
                //  Guardar datos para confirmación
                $_SESSION['reserva_confirmada'] = [
                    'folio' => $folio,
                    'id_reserva' => $id_reserva,
                    'id_vuelo' => $id_vuelo
                ];
                
                // Redirigir a página de confirmación
                header("Location: confirmacion.php?folio=" . $folio);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error al procesar la reserva: " . $e->getMessage();
            }
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- EL HTML PERMANECE IGUAL HASTA EL FORMULARIO -->
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Selección de asientos — Reserva</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container mt-4">
    <!-- Mensajes de error -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <?php foreach($errors as $error): ?>
          <div><?= esc($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
<div class="plane-container">
  <!-- SVG del avión como fondo -->

  <div class="layout">
    <!-- Columna de asientos -->
    <div class="seat-column">
      <?php foreach($seat_rows as $rowNum => $row): ?>
        <div class="single-row" data-row="<?= $rowNum ?>">
          <div class="row-number"><strong><?= $rowNum ?></strong></div>
          <div class="seats">
            <?php
              $cols = array_keys($row['seats']);
              $count = count($cols); 
              $i = 0;
              foreach ($cols as $col) {
                $i++;
                $seatCode = $rowNum . $col;
                $status = $row['seats'][$col];
                $classes = 'seat ';
                $classes .= $status === 'X' ? 'occupied' : 'available';
                echo "<div class='{$classes}' data-seat='" . esc($seatCode) . "' data-price='{$row['price']}'>" . esc($col) . "</div>";
                
                // Agregar pasillo si es necesario
                if (($count == 4 && $i == 2) || ($count == 6 && $i == 3)) {
                  echo "<div class='aisle'></div>";
                }
              }
            ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Panel derecho -->
    <div class="right-panel">
      <div class="card">
        <div class="card-body">
          <h5>Resumen</h5>
          <p><strong>Vuelo:</strong> <?= esc($vuelo['origen']) ?> → <?= esc($vuelo['destino']) ?></p>
          <p><strong>Pasajeros:</strong> <?= count($pasajeros) ?></p>
          
          <form method="post" id="reserveForm">
            <div class="mb-3">
              <label>Asignar asiento a:</label>
              <select id="assignPassenger" class="form-select">
                <?php foreach($pasajeros as $idx => $p): ?>
                  <option value="<?= $idx + 1 ?>">
                    <?= ($idx + 1) ?> - <?= esc($p['nombre'] . ' ' . $p['apellidos']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div id="selectedList" class="mb-3"></div>
            <div id="hiddenInputs"></div>

            <button type="submit" name="reserve_submit" class="btn btn-primary w-100">
              Confirmar Reserva
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

  <script>
      document.addEventListener('DOMContentLoaded', function() {
        const seats = document.querySelectorAll('.seat.available');
        const assignSelect = document.getElementById('assignPassenger');
        const selectedList = document.getElementById('selectedList');
        const hiddenInputs = document.getElementById('hiddenInputs');
        
        let selectedSeats = {};
        let passengerSeatMap = {}; // Nuevo: para rastrear asientos por pasajero

        seats.forEach(seat => {
          seat.addEventListener('click', function() {
            const seatCode = this.dataset.seat;
            const passengerIdx = assignSelect.value;
            
            // Verificar si el pasajero ya tiene un asiento asignado
            if (passengerSeatMap[passengerIdx] && passengerSeatMap[passengerIdx] !== seatCode) {
              alert('Este pasajero ya tiene un asiento asignado. Por favor, seleccione otro pasajero o deseleccione el asiento actual.');
              return;
            }
            
            // Verificar si el asiento ya está ocupado por otro pasajero
            if (selectedSeats[seatCode] && selectedSeats[seatCode].passengerIdx !== passengerIdx) {
              alert('Este asiento ya está asignado a otro pasajero.');
              return;
            }

            if (selectedSeats[seatCode]) {
              // Deseleccionar
              delete selectedSeats[seatCode];
              delete passengerSeatMap[passengerIdx];
              this.classList.remove('selected');
            } else {
              // Seleccionar
              const passengerName = assignSelect.options[assignSelect.selectedIndex].text;
              
              // Si el pasajero ya tenía un asiento, limpiar el anterior
              if (passengerSeatMap[passengerIdx]) {
                const previousSeatCode = passengerSeatMap[passengerIdx];
                const previousSeat = document.querySelector(`.seat[data-seat="${previousSeatCode}"]`);
                if (previousSeat) {
                  previousSeat.classList.remove('selected');
                }
                delete selectedSeats[previousSeatCode];
              }
              
              selectedSeats[seatCode] = {
                passengerIdx: passengerIdx,
                passengerName: passengerName
              };
              passengerSeatMap[passengerIdx] = seatCode;
              this.classList.add('selected');
            }
            
            updateSelectionUI();
          });
        });

        // Actualizar la interfaz cuando se cambie el pasajero seleccionado
        assignSelect.addEventListener('change', function() {
          // Resaltar el asiento del pasajero actual si tiene uno
          const currentPassenger = this.value;
          const currentSeat = passengerSeatMap[currentPassenger];
          
          // Remover resaltado de todos los asientos
          document.querySelectorAll('.seat.selected').forEach(seat => {
            seat.classList.remove('selected-highlight');
          });
          
          // Resaltar el asiento del pasajero actual
          if (currentSeat) {
            const seatElement = document.querySelector(`.seat[data-seat="${currentSeat}"]`);
            if (seatElement) {
              seatElement.classList.add('selected-highlight');
            }
          }
        });

        function updateSelectionUI() {
          selectedList.innerHTML = '';
          hiddenInputs.innerHTML = '';
          
          Object.keys(selectedSeats).forEach(seatCode => {
            const data = selectedSeats[seatCode];
            
            // Agregar a lista visible
            const div = document.createElement('div');
            div.className = 'alert alert-info py-2';
            div.innerHTML = `<strong>${seatCode}</strong> - ${data.passengerName}`;
            selectedList.appendChild(div);
            
            // Agregar inputs hidden
            const inputSeat = document.createElement('input');
            inputSeat.type = 'hidden';
            inputSeat.name = 'selected_seats[]';
            inputSeat.value = seatCode;
            hiddenInputs.appendChild(inputSeat);
            
            const inputPass = document.createElement('input');
            inputPass.type = 'hidden';
            inputPass.name = `passenger_for_seat[${seatCode}]`;
            inputPass.value = data.passengerIdx;
            hiddenInputs.appendChild(inputPass);
          });
        }
      });
  </script>

</body>

</html>
