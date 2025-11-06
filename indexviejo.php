<?php
// index.php
// Simple mock of an airline homepage - booking form processed with PHP (mock results).
// Note: This is a demo/template. Replace placeholder images and texts with your own assets.

function sanitize($s){ return htmlspecialchars(trim($s)); }

// If form submitted, grab values and create a mock "search result"
$searchResults = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_submit'])) {
    $from = sanitize($_POST['from'] ?? '');
    $to = sanitize($_POST['to'] ?? '');
    $dep = sanitize($_POST['dep_date'] ?? '');
    $ret = sanitize($_POST['ret_date'] ?? '');
    $pass = intval($_POST['passengers'] ?? 1);

    // Simple validation
    $errors = [];
    if ($from === '' || $to === '') $errors[] = "Origen y destino son obligatorios.";
    if ($from === $to) $errors[] = "Origen y destino no pueden ser el mismo.";
    if ($dep === '') $errors[] = "Fecha de salida obligatoria.";
    if ($pass < 1) $errors[] = "Máscara de pasajeros inválida.";

    if (empty($errors)) {
        // Generate some mock flight results
        $searchResults = [
            'query' => compact('from','to','dep','ret','pass'),
            'flights' => []
        ];
        // Create 3 fake offers
        for ($i=1; $i<=3; $i++) {
            $price = rand(220, 899) + ($pass * 50);
            $searchResults['flights'][] = [
                'airline' => 'DemoAir',
                'flight_no' => 'DA'.rand(100,999),
                'depart_time' => date('H:i', strtotime("+".($i*2)." hours")),
                'arrive_time' => date('H:i', strtotime("+".($i*2+3)." hours")),
                'duration' => (($i*2)+3) . "h 00m",
                'stops' => ($i===1)?'Directo':'1 escala',
                'price' => number_format($price, 2),
                'class' => ($i===1)?'Económica':'Económica Plus'
            ];
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>AereoCherry - Reserva de vuelos</title>

  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* Palette inspired by typical airline: deep blue + white + accent */
    :root{
      --brand:#013A63;
      --accent:#0077C8;
    }
    body{ font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .navbar{ background: linear-gradient(90deg,var(--brand), #004a7a);}
    .navbar .nav-link, .navbar .navbar-brand, .navbar .btn { color: #fff !important;}
    .hero {
      background: linear-gradient(180deg, rgba(1,58,99,0.85), rgba(0,50,80,0.7)), url('https://picsum.photos/1400/600?blur=3') center/cover no-repeat;
      color: white;
      padding: 48px 0;
    }
    .booking-card { background: rgba(255,255,255,0.98); border-radius: 12px; padding: 18px; box-shadow: 0 6px 28px rgba(0,0,0,0.18);}
    .promo-card img{ border-top-left-radius: .5rem; border-top-right-radius: .5rem; height:160px; object-fit: cover;}
    .footer { background:#0b2540; color: #cfe9ff; padding: 32px 0; }
    .offer-badge { background: var(--accent); color: white; padding: 4px 8px; border-radius: 20px; font-weight:600; }
    @media (min-width: 992px){
      .booking-card { max-width: 920px; margin: -80px auto 0; }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <div style="width:44px;height:44px;border-radius:8px;background:#ffffff14;display:flex;align-items:center;justify-content:center;margin-right:10px">
        <!-- Placeholder for logo -->
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff"><path d="M2 12h20" stroke-width="2"/></svg>
      </div>
      <strong>AereoCherry</strong>
    </a>

    <button class="navbar-toggler btn btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto me-3">
        <li class="nav-item"><a class="nav-link" href="#">Vuelos</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Destinos</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Check-in</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Estado de vuelo</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-outline-light me-2" href="#">Iniciar sesión</a>
        <a class="btn btn-light text-dark" href="#">Registrarse</a>
      </div>
    </div>
  </div>
</nav>

<!-- Hero with Booking widget -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <h1 class="display-5 fw-bold">Encuentra las mejores tarifas a tu destino</h1>
        <p class="lead">Busca, compara y reserva. Cancelación flexible y opciones de equipaje.</p>
      </div>

      <div class="col-lg-5">
        <div class="booking-card">
          <form method="post" novalidate>
            <div class="row g-2">
              <div class="col-12 col-md-6">
                <label class="form-label">Origen</label>
                <input name="from" class="form-control" placeholder="CDMX (MEX)" required>
                <small class="text-muted">Punto de origen</small>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Destino</label>
                <input name="to" class="form-control" placeholder="Monterrey (MTY)" required>
                <small class="text-muted">Punto de destino</small>
              </div>

              <div class="col-6 col-md-6">
                <label class="form-label">Salida</label>
                <input name="dep_date" type="date" class="form-control" required>
                <small class="text-muted">Fecha de salida</small>
              </div>
              <div class="col-6 col-md-6">
                <label class="form-label">Regreso (opcional)</label>
                <input name="ret_date" type="date" class="form-control">
                <small class="text-muted">Fecha de regreso (opcional)</small>
              </div>

              <div class="col-6 col-md-6">
                <label class="form-label">Pasajeros</label>

                <select name="passengers" class="form-select">
                  <option value="1">1 adulto</option>
                  <option value="2">2 adultos</option>
                  <option value="3">3 adultos</option>
                  <option value="4">4 adultos</option>
                </select>
                <small class="text-muted">Número de pasajeros</small>
              </div>

              <div class="col-6 col-md-6 d-flex align-items-end">
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
</section>

<!-- Search results (mock) -->
<?php if ($searchResults): ?>
  <section class="container my-5">
    <h3>Resultados para: <?= "{$searchResults['query']['from']} → {$searchResults['query']['to']}" ?></h3>
    <div class="row">
      <?php foreach($searchResults['flights'] as $f): ?>
        <div class="col-md-4">
          <div class="card mb-3 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <strong><?= $f['airline'] ?></strong>
                <span class="offer-badge">$<?= $f['price'] ?></span>
              </div>
              <div class="mt-2">
                <div><small>Vuelo</small> <div><?= $f['flight_no'] ?> · <?= $f['class'] ?></div></div>
                <div class="mt-2"><small>Salida</small><div><?= $f['depart_time'] ?> → <?= $f['arrive_time'] ?></div></div>
                <div class="mt-2"><small>Duración</small><div><?= $f['duration'] ?> · <?= $f['stops'] ?></div></div>
              </div>
              <div class="mt-3 d-grid">
                <a href="#" class="btn btn-outline-primary">Seleccionar</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Promotions / Cards -->
<section class="container my-5">
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

<!-- Carousel (Bootstrap) -->
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

<!-- Features -->
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
<footer class="footer mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-4">
        <h5>DemoAer</h5>
        <p class="small">Empresa de demostración — sitio de muestra para diseño.</p>
      </div>
      <div class="col-md-4">
        <h6>Enlaces</h6>
        <ul class="list-unstyled small">
          <li><a href="#" class="text-decoration-none text-light">Contacto</a></li>
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
      &copy; <?= date('Y') ?> DemoAer. Todos los derechos reservados.
    </div>
  </div>
</footer>

<!-- Bootstrap JS (bundle includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Small JS: example of enhancing UX (clear placeholder) -->
<script>
  // Example: swap origin/destination quickly
  document.addEventListener('DOMContentLoaded', function(){
    // No external dependencies — you can add more interactivity here
  });
</script>

</body>
</html>
