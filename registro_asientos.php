<?php
// registro.php - Registro de pasajeros
//require_once 'config.php';
require_once '../config.php';
session_start(); // Asegurar que la sesión esté iniciada

function sanitize($s) { return htmlspecialchars(trim($s)); }

$id_vuelo = $_GET['id_vuelo'] ?? '';
$errors = [];
$success = '';

// Validar que existe el vuelo
if (empty($id_vuelo)) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM vuelos WHERE id_vuelo = ?");
    $stmt->execute([$id_vuelo]);
    $vuelo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vuelo) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $errors[] = "Error al cargar información del vuelo.";
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservar_submit'])) {
    // Obtener datos del formulario
    $pasajeros = [];
    
    // Datos del contacto principal (siempre existe)
    if (!empty($_POST['categoria_0']) && !empty($_POST['nombre_0']) && !empty($_POST['apellidos_0']) && 
        !empty($_POST['correo_0']) && !empty($_POST['telefono_0'])) {
        
        $pasajeros[] = [
            'categoria' => sanitize($_POST['categoria_0']),
            'nombre' => sanitize($_POST['nombre_0']),
            'apellidos' => sanitize($_POST['apellidos_0']),
            'correo' => sanitize($_POST['correo_0']),
            'telefono' => sanitize($_POST['telefono_0']),
            'es_contacto' => true
        ];
    } else {
        $errors[] = "Los datos del pasajero principal son obligatorios.";
    }
    
    // Datos de pasajeros adicionales
    $pasajero_count = $_POST['pasajero_count'] ?? 1;
    for ($i = 1; $i < $pasajero_count; $i++) {
        if (!empty($_POST["nombre_$i"]) && !empty($_POST["apellidos_$i"]) && !empty($_POST["categoria_$i"])) {
            $pasajeros[] = [
                'categoria' => sanitize($_POST["categoria_$i"]),
                'nombre' => sanitize($_POST["nombre_$i"]),
                'apellidos' => sanitize($_POST["apellidos_$i"]),
                'correo' => '',
                'telefono' => '',
                'es_contacto' => false
            ];
        }
    }
    
    // Validaciones
    if (count($pasajeros) < 1) {
        $errors[] = "Debe registrar al menos un pasajero.";
    }
    
    if (count($pasajeros) > 11) {
        $errors[] = "Máximo 11 pasajeros por reservación.";
    }
    
    // Si no hay errores, redirigir a selección de asientos
    if (empty($errors)) {
        $_SESSION['reserva_temp'] = [
            'id_vuelo' => $id_vuelo,
            'pasajeros' => $pasajeros
        ];
        header("Location: asientos.php");
        exit;
    }
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registro de Pasajeros - Cherry Airlines</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-cherry">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <div class="brand-logo">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff">
          <path d="M2 12h20" stroke-width="2"/>
        </svg>
      </div>
      <strong>Cherry Airlines</strong>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php#promociones">Promociones</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#contacto">Contacto</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Contenido Principal -->
<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        
        <!-- Información del Vuelo -->
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Información del Vuelo Seleccionado</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <strong>Origen:</strong> <?= htmlspecialchars($vuelo['origen']) ?><br>
                <strong>Destino:</strong> <?= htmlspecialchars($vuelo['destino']) ?>
              </div>
              <div class="col-md-6">
                <strong>Salida:</strong> <?= date('d/m/Y H:i', strtotime($vuelo['salida'])) ?><br>
                <strong>Llegada:</strong> <?= date('d/m/Y H:i', strtotime($vuelo['llegada'])) ?>
              </div>
            </div>
            <div class="mt-2">
              <strong>Precio base:</strong> $<?= number_format($vuelo['precio'], 2) ?>
            </div>
          </div>
        </div>

        <!-- Formulario de Registro -->
        <div class="card shadow-sm">
          <div class="card-header bg-cherry text-white">
            <h5 class="mb-0">Registro de Pasajeros</h5>
          </div>
          <div class="card-body">
            
            <?php if (!empty($errors)): ?>
              <div class="alert alert-danger">
                <?php foreach($errors as $error): ?>
                  <div><?= $error ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="post" id="registroForm">
              <input type="hidden" name="pasajero_count" id="pasajero_count" value="1">
              
              <!-- Pasajero Principal -->
              <div class="pasajero-form mb-4 p-4 border rounded" id="pasajero-0">
                <h6 class="text-cherry mb-3">Pasajero Principal (Contacto)</h6>
                
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Categoría de Pasajero *</label>
                    <select name="categoria_0" class="form-select" required>
                      <option value="">Seleccione categoría</option>
                      <option value="1">Bebé (0-2 años)</option>
                      <option value="2">Niño (3-17 años)</option>
                      <option value="3">Adulto (18-59 años)</option>
                      <option value="4">Adulto mayor (60+ años)</option>
                    </select>
                  </div>
                  
                  <div class="col-md-6">
                    <label class="form-label">Nombre(s) *</label>
                    <input type="text" name="nombre_0" class="form-control" placeholder="Ingrese nombre(s)" required>
                  </div>
                  
                  <div class="col-md-6">
                    <label class="form-label">Apellido(s) *</label>
                    <input type="text" name="apellidos_0" class="form-control" placeholder="Ingrese apellido(s)" required>
                  </div>
                  
                  <div class="col-md-6">
                    <label class="form-label">Correo Electrónico *</label>
                    <input type="email" name="correo_0" class="form-control" placeholder="ejemplo@correo.com" required>
                  </div>
                  
                  <div class="col-md-6">
                    <label class="form-label">Teléfono *</label>
                    <input type="tel" name="telefono_0" class="form-control" placeholder="+52 123 456 7890" required>
                  </div>
                </div>
              </div>

              <!-- Contenedor para pasajeros adicionales -->
              <div id="pasajeros-adicionales"></div>

              <!-- Botón para agregar más pasajeros -->
              <div class="text-center mb-4">
                <button type="button" id="agregar-pasajero" class="btn btn-outline-cherry">
                  <svg width="16" height="16" fill="currentColor" class="me-1">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                  </svg>
                  + Agregar Pasajero
                </button>
                <small class="d-block text-muted mt-1">Máximo 11 pasajeros en total</small>
              </div>

              <!-- Botón para continuar -->
              <div class="text-center">
                <button type="submit" name="reservar_submit" class="btn btn-primary btn-lg">
                  Continuar a Selección de Asientos
                </button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer-cherry mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-4">
        <h5>Cherry Airlines</h5>
        <p class="small">Tu aerolínea de confianza para viajes nacionales e internacionales.</p>
      </div>
      <div class="col-md-4">
        <h6>Enlaces</h6>
        <ul class="list-unstyled small">
          <li><a href="index.php#contacto" class="text-decoration-none text-light">Contacto</a></li>
          <li><a href="#" class="text-decoration-none text-light">Términos y condiciones</a></li>
          <li><a href="#" class="text-decoration-none text-light">Privacidad</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h6>Síguenos</h6>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-light btn-sm" href="#">Twitter</a>
          <a class="btn btn-outline-light btn-sm" href="#">Facebook</a>
        </div>
      </div>
    </div>

    <div class="text-center mt-4 small">
      &copy; <?= date('Y') ?> Cherry Airlines. Todos los derechos reservados.
    </div>
  </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let pasajeroCount = 1;
    const maxPasajeros = 11;
    const contenedor = document.getElementById('pasajeros-adicionales');
    const btnAgregar = document.getElementById('agregar-pasajero');
    const inputCount = document.getElementById('pasajero_count');

    btnAgregar.addEventListener('click', function() {
        if (pasajeroCount >= maxPasajeros) {
            alert('Máximo ' + maxPasajeros + ' pasajeros por reservación');
            return;
        }

        const nuevoPasajero = document.createElement('div');
        nuevoPasajero.className = 'pasajero-form mb-4 p-4 border rounded';
        nuevoPasajero.id = `pasajero-${pasajeroCount}`;
        
        nuevoPasajero.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="text-cherry mb-0">Pasajero Adicional ${pasajeroCount}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger quitar-pasajero" data-id="${pasajeroCount}">
                    <svg width="14" height="14" fill="currentColor">
                        <path d="M2.5 1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H3v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h.5a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1H2.5zm3 4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5zM8 5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7A.5.5 0 0 1 8 5zm3 .5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 1 0z"/>
                    </svg>
                    Quitar
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Categoría de Pasajero *</label>
                    <select name="categoria_${pasajeroCount}" class="form-select" required>
                        <option value="">Seleccione categoría</option>
                        <option value="1">Bebé (0-2 años)</option>
                        <option value="2">Niño (3-17 años)</option>
                        <option value="3">Adulto (18-59 años)</option>
                        <option value="4">Adulto mayor (60+ años)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nombre(s) *</label>
                    <input type="text" name="nombre_${pasajeroCount}" class="form-control" placeholder="Ingrese nombre(s)" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apellido(s) *</label>
                    <input type="text" name="apellidos_${pasajeroCount}" class="form-control" placeholder="Ingrese apellido(s)" required>
                </div>
            </div>
        `;

        contenedor.appendChild(nuevoPasajero);
        pasajeroCount++;
        inputCount.value = pasajeroCount;

        // Actualizar eventos de botones quitar
        actualizarEventosQuitar();
    });

    function actualizarEventosQuitar() {
        document.querySelectorAll('.quitar-pasajero').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const elemento = document.getElementById(`pasajero-${id}`);
                if (elemento) {
                    elemento.remove();
                    pasajeroCount--;
                    inputCount.value = pasajeroCount;
                    reordenarPasajeros();
                }
            });
        });
    }

    function reordenarPasajeros() {
        // Esta función puede expandirse si se necesita reordenar los índices
    }
});
</script>

</body>
</html>
