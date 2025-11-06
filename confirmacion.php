<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Información</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <link rel="stylesheet" href="styles.css"> 
</head>

<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-cherry mb-5">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <div class="brand-logo">
                <i class="bi bi-airplane-fill"></i>
            </div>
            Cherry Airlines
        </a>
    </div>
</nav>

<div class="container">

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- CARD DE CONSULTA -->
            <div class="booking-card shadow mb-4">
                <h4 class="mb-3 text-center">
                    <i class="bi bi-search"></i> Consultar mi Reserva
                </h4>

                <form method="POST" action="confirmar_info.php">

                    <p class="text-muted mb-3 text-center">
                        Ingrese <strong>correo electrónico</strong> o <strong>teléfono</strong>.
                    </p>

                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="correo" class="form-control"
                               placeholder="usuario@correo.com">
                    </div>

                    <div class="text-center my-2">
                        <strong>o</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Número telefónico</label>
                        <input type="text" name="telefono" class="form-control"
                               placeholder="Ejemplo: 3121234567">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-check-circle"></i> Buscar Reserva
                    </button>

                </form>

                <!-- ERROR -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

            </div> <!-- booking-card -->

            <!-- RESULTADOS -->
            <?php if ($reservas): ?>
                <h4 class="mt-4 mb-3 text-center">Reservas encontradas</h4>

                <?php foreach ($reservas as $reserva): ?>

                    <div class="booking-card shadow mb-4">

                        <h5 class="mb-3">
                            <i class="bi bi-file-earmark-text"></i> Folio:
                            <strong><?= $reserva["folio"] ?></strong>
                        </h5>

                        <p><strong>Nombre:</strong> <?= $reserva["nombre_contacto"] ?></p>
                        <p><strong>Correo:</strong> <?= $reserva["correo_contacto"] ?></p>
                        <p><strong>Teléfono:</strong> <?= $reserva["telefono_contacto"] ?></p>

                        <hr>

                        <!-- Vuelo de ida -->
                        <h6 class="text-cherry"><i class="bi bi-airplane-fill"></i> Vuelo de ida</h6>
                        <p><strong>Origen:</strong> <?= $reserva["origen_ida"] ?></p>
                        <p><strong>Destino:</strong> <?= $reserva["destino_ida"] ?></p>
                        <p><strong>Salida:</strong> <?= $reserva["salida_ida"] ?></p>
                        <p><strong>Llegada:</strong> <?= $reserva["llegada_ida"] ?></p>

                        <?php if ($reserva["id_vuelo_regreso"]): ?>
                            <hr>

                            <h6 class="text-cherry"><i class="bi bi-airplane"></i> Vuelo de regreso</h6>
                            <p><strong>Origen:</strong> <?= $reserva["origen_regreso"] ?></p>
                            <p><strong>Destino:</strong> <?= $reserva["destino_regreso"] ?></p>
                            <p><strong>Salida:</strong> <?= $reserva["salida_regreso"] ?></p>
                            <p><strong>Llegada:</strong> <?= $reserva["llegada_regreso"] ?></p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<!-- FOOTER -->
<footer class="footer-cherry mt-5 text-center">
    <p>&copy; <?= date("Y") ?> Cherry Airlines — Elegancia en el aire ✈️</p>
</footer>

</body>
</html>
