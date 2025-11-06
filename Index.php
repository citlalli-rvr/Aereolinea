<?php
// index.php - Cherry Airlines
require_once 'config.php';

function sanitize($s){ return htmlspecialchars(trim($s)); }

// Si se envió el formulario de búsqueda por folio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folio_submit'])) {
    $folio = sanitize($_POST['folio'] ?? '');
    if (!empty($folio)) {
        header("Location: confirmacion.php?folio=" . urlencode($folio));
        exit;
    }
}

// Si se envió el formulario de búsqueda de vuelos
$searchResults = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_submit'])) {
    $from = sanitize($_POST['from'] ?? '');
    $to = sanitize($_POST['to'] ?? '');
    $dep = sanitize($_POST['dep_date'] ?? '');
    $ret = sanitize($_POST['ret_date'] ?? '');

    // Validación simple
    $errors = [];
    if ($from === '' || $to === '') $errors[] = "Origen y destino son obligatorios.";
    if ($from === $to) $errors[] = "Origen y destino no pueden ser el mismo.";
    if ($dep === '') $errors[] = "Fecha de salida obligatoria.";

    if (empty($errors)) {
        // Buscar vuelos reales en la base de datos
        try {
            // Convertir fecha al formato de la BD
            $dep_mysql = date('Y-m-d', strtotime($dep));
            
            $stmt = $pdo->prepare("
                SELECT * FROM vuelos 
                WHERE origen LIKE ? AND destino LIKE ? AND DATE(salida) = ?
                ORDER BY salida ASC
            ");
            $stmt->execute(["%$from%", "%$to%", $dep_mysql]);
            $vuelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($vuelos) {
                $searchResults = [
                    'query' => compact('from','to','dep','ret'),
                    'flights' => $vuelos
                ];
            } else {
                $errors[] = "No se encontraron vuelos para la ruta y fecha seleccionadas.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error en la búsqueda: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cherry Airlines - Reserva de vuelos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
        <li class="nav-item"><a class="nav-link" href="#promociones">Promociones</a></li>
        <li class="nav-item"><a class="nav-link" href="#contacto">Contacto</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero con formulario de búsqueda -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <h1 class="display-5 fw-bold">Encuentra las mejores tarifas a tu destino</h1>
        <p class="lead">Busca, compara y reserva. Cancelación flexible y opciones de equipaje.</p>
      </div>

      <div class="col-lg-6">
        <div class="row g-4">
          <!-- Sección de búsqueda por folio -->
          <div class="col-12">
            <div class="booking-card">
              <h6 class="mb-3">Buscar reservación por folio</h6>
              <form method="post" novalidate>
                <div class="row g-2">
                  <div class="col-8">
                    <input name="folio" class="form-control" placeholder="Ingresa tu folio" required>
                  </div>
                  <div class="col-4">
                    <button name="folio_submit" class="btn btn-secondary w-100">Buscar</button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <!-- Formulario principal de búsqueda -->
          <div class="col-12">
            <div class="booking-card">
              <form method="post" novalidate>
                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label">Origen</label>
                    <input name="from" class="form-control" placeholder="Ej: Ciudad de México" required value="<?= $_POST['from'] ?? '' ?>">
                    <small class="text-muted">Punto de origen</small>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Destino</label>
                    <input name="to" class="form-control" placeholder="Ej: Monterrey" required value="<?= $_POST['to'] ?? '' ?>">
                    <small class="text-muted">Punto de destino</small>
                  </div>

                  <div class="col-6 col-md-6">
                    <label class="form-label">Salida</label>
                    <input name="dep_date" type="date" class="form-control" required 
                           min="<?= date('Y-m-d') ?>" value="<?= $_POST['dep_date'] ?? '' ?>">
                    <small class="text-muted">Fecha de salida</small>
                  </div>
                  <div class="col-6 col-md-6">
                    <label class="form-label">Regreso (opcional)</label>
                    <input name="ret_date" type="date" class="form-control" 
                           min="<?= date('Y-m-d') ?>" value="<?= $_POST['ret_date'] ?? '' ?>">
                    <small class="text-muted">Fecha de regreso (opcional)</small>
                  </div>

                  <div class="col-12 d-flex align-items-end">
                    <button name="search_submit" class="btn btn-primary w-100">Buscar vuelos</button>
                  </div>
                </div>
              </form>

              <?php if (!empty($errors)): ?>
                <div class="mt-3 alert alert-danger">
                  <?php foreach($errors as $e) echo "<div>$e</div>"; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Resultados de búsqueda -->
<?php if ($searchResults): ?>
  <section class="container my-5">
    <h3>Resultados para: <?= "{$searchResults['query']['from']} → {$searchResults['query']['to']}" ?></h3>
    <p class="text-muted">Fecha: <?= date('d/m/Y', strtotime($searchResults['query']['dep'])) ?></p>
    
    <div class="row">
      <?php foreach($searchResults['flights'] as $f): ?>
        <div class="col-md-4 mb-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <strong class="h5">Cherry Airlines</strong>
                <span class="offer-badge">$<?= number_format($f['precio'], 2) ?></span>
              </div>
              
              <div class="flight-info">
                <div class="mb-2">
                  <small class="text-muted">Vuelo</small>
                  <div class="fw-bold">#<?= $f['id_vuelo'] ?></div>
                </div>
                
                <div class="mb-2">
                  <small class="text-muted">Horario</small>
                  <div>
                    <?= date('H:i', strtotime($f['salida'])) ?> → <?= date('H:i', strtotime($f['llegada'])) ?>
                  </div>
                </div>
                
                <div class="mb-3">
                  <small class="text-muted">Duración</small>
                  <div>
                    <?php 
                    $diff = strtotime($f['llegada']) - strtotime($f['salida']);
                    $hours = floor($diff / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    echo "{$hours}h {$minutes}m";
                    ?>
                  </div>
                </div>
                
                <div class="route-info">
                  <div class="fw-bold"><?= $f['origen'] ?></div>
                  <div class="small text-muted">a</div>
                  <div class="fw-bold"><?= $f['destino'] ?></div>
                </div>
              </div>
              
              <div class="mt-4 d-grid">
                <a href="registro.php?id_vuelo=<?= $f['id_vuelo'] ?>" class="btn btn-primary">Seleccionar Vuelo</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Resto del código permanece igual -->
<!-- Promociones -->
<section id="promociones" class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Promociones</h4>
    <small class="text-muted">Ofertas seleccionadas</small>
  </div>
  <div class="row g-3">
    <?php for($i=1;$i<=3;$i++): ?>
    <div class="col-md-4">
      <div class="card promo-card h-100">
        <img src="https://picsum.photos/600/300?random=<?= $i ?>" class="card-img-top" alt="destino">
        <div class="card-body">
          <h5 class="card-title">Descuento a Destino <?= $i ?></h5>
          <p class="card-text">Tarifas promocionales, condiciones aplican. Reserva antes de <?= date('d M Y', strtotime("+".($i*7)." days")) ?>.</p>
          <a href="#" class="btn btn-primary">Ver oferta</a>
        </div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</section>

<!-- Carousel -->
<section class="container my-5">
  <div id="offersCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner rounded">
      <div class="carousel-item active">
        <img src="https://picsum.photos/1400/400?landscape=1" class="d-block w-100" alt="...">
      </div>
      <div class="carousel-item">
        <img src="https://picsum.photos/1400/400?landscape=2" class="d-block w-100" alt="...">
      </div>
      <div class="carousel-item">
        <img src="https://picsum.photos/1400/400?landscape=3" class="d-block w-100" alt="...">
      </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#offersCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#offersCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
    </button>
  </div>
</section>

<!-- Características -->
<section class="container my-5">
  <div class="row text-center">
    <div class="col-md-4 mb-3">
      <div class="p-3 border rounded">
        <h5>Equipaje incluido</h5>
        <p class="small text-muted">Elige según tu tarifa y equipaje adicional.</p>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="p-3 border rounded">
        <h5>Check-in online</h5>
        <p class="small text-muted">Evita filas, realiza tu check-in desde casa.</p>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="p-3 border rounded">
        <h5>Estado del vuelo</h5>
        <p class="small text-muted">Consulta información en tiempo real.</p>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer id="contacto" class="footer-cherry mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-4">
        <h5>Cherry Airlines</h5>
        <p class="small">Tu aerolínea de confianza para viajes nacionales e internacionales.</p>
      </div>
      <div class="col-md-4">
        <h6>Enlaces</h6>
        <ul class="list-unstyled small">
          <li><a href="#contacto" class="text-decoration-none text-light">Contacto</a></li>
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

</body>
</html>
