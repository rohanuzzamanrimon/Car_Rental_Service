<?php
session_start();
ob_start();

/*if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}*/

$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Build dynamic SQL based on search/filter GET params
$sql = "(SELECT id, model, brand, type, year, price, description, features, image, availability, booked FROM cars WHERE availability=1)
        UNION
        (SELECT id, model, brand, type, year, price, description, features, image, availability, booked FROM owner_cars WHERE availability=1 AND approved=1)";
$params = [];

// Search query (q)
if (!empty($_GET['q'])) {
    $q = '%' . trim($_GET['q']) . '%';
    $sql .= " AND (model LIKE ? OR type LIKE ?)";
    $params[] = $q;
    $params[] = $q;
}

// Type filter (type)
if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
    $sql .= " AND LOWER(type) = LOWER(?)";
    $params[] = $_GET['type'];
}

// Price sort (sort)
$sort = $_GET['sort'] ?? '';
if ($sort === 'high') {
    $sql .= " ORDER BY price DESC";
} else {
    $sql .= " ORDER BY price ASC";
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book'])) {
    if (!isset($_SESSION['user_id'])) {
        // Save intended action and redirect to login
        $_SESSION['redirect_after_login'] = 'dashboard.php';
        header("Location: login.php");
        exit();
    }

    $car_id = $_POST['car_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if (strtotime($end_date) > strtotime($start_date)) {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, car_id, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $car_id, $start_date, $end_date]);
            $stmt = $conn->prepare("UPDATE cars SET booked = 1 WHERE id = ?");
            $stmt->execute([$car_id]);
            $conn->commit();
            $_SESSION['booking_success'] = "Booking confirmed! Car reserved from $start_date to $end_date.";
            header("Location: dashboard.php"); // or replace with your current filename
            exit();

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Booking failed: " . $e->getMessage();
        }
    } else {
        $error = "End date must be after start date.";
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedy Cars - Post-Login Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<section class="hero" id="home">
    <div class="hero-content">
        <h1 class="animated-heading">
          <span class="text-rotation">
            <span class="text">Redefining Luxury Rentals</span>
            <span class="text">Experience Premium Travel</span>
            <span class="text">Drive 4Your Dreams Today</span>
            <span class="text">Luxury at Your Fingertips</span>
          </span>
        </h1>
        <p>Elevate your journey with our handpicked collection of extraordinary vehicles.</p>
        <button class="cta-btn">Discover Now</button>
    </div>
</section>
<section class="fleet-section" id="fleet">
    <h2>Our Exquisite Fleet</h2>
    <form class="filter-bar" method="GET" action="dashboard.php" style="display:flex;gap:12px;margin-bottom:24px;">
        <select onchange="this.form.submit()" name="type">
            <option value="all" <?php if(empty($_GET['type']) || $_GET['type']=='all') echo 'selected'; ?>>All Vehicles</option>
            <option value="sedan" <?php if(!empty($_GET['type']) && $_GET['type']=='sedan') echo 'selected'; ?>>Sedan</option>
            <option value="suv" <?php if(!empty($_GET['type']) && $_GET['type']=='suv') echo 'selected'; ?>>SUV</option>
            <option value="electric" <?php if(!empty($_GET['type']) && $_GET['type']=='electric') echo 'selected'; ?>>Electric</option>
        </select>
        <select onchange="this.form.submit()" name="sort">
            <option value="low" <?php if(empty($_GET['sort']) || $_GET['sort']=='low') echo 'selected'; ?>>Low to High</option>
            <option value="high" <?php if(!empty($_GET['sort']) && $_GET['sort']=='high') echo 'selected'; ?>>High to Low</option>
        </select>
    </form>
    <div class="car-grid">
        <?php foreach ($cars as $car): ?>
            <div class="car-card">
            <?php
                $imagePath = '/car_rental/uploads/cars/default-car.jpg';
                if (!empty($car['image'])) {
                    $imagePath = '/car_rental/uploads/cars/' . $car['image'];
                    $physicalPath = $_SERVER['DOCUMENT_ROOT'] . $imagePath;
                    if (!file_exists($physicalPath)) {
                        $imagePath = '/car_rental/uploads/cars/default-car.jpg';
                    }
                }
            ?>
                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($car['model']); ?>">
                <div class="car-card-content">
                    <h3><a href="car-detail.php?id=<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['model']); ?></a></h3>
                    <p>৳<?php echo htmlspecialchars($car['price']); ?>/day</p>
                    <button class="reserve-btn" data-car-id="<?php echo $car['id']; ?>">Reserve Now</button>
                </div>
                <?php if (isset($car['owner_id'])): ?>
                    <a href="owner_profile.php?id=<?= $car['owner_id'] ?>">View Owner Profile</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php
$stmt = $conn->query("SELECT * FROM routes ORDER BY id DESC");
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="routes-section" id="routes">
  <div class="routes-header">
    <h2>Popular Routes</h2>
    <p>Plan your trip with ease. See cost estimates and book instantly for stress-free travel!</p>
  </div>
  <div class="routes-list">
    <?php foreach ($routes as $route): ?>
    <a href="cars-by-route.php?route_id=<?= $route['id'] ?>" class="route-card" style="text-decoration:none;">
      <span class="route-icon">🚗</span>
      <span class="route-from"><?= htmlspecialchars($route['route_from']) ?></span>
      <span class="route-to">→ <?= htmlspecialchars($route['route_to']) ?></span>
      <span class="route-price">Starts from ৳<?= htmlspecialchars($route['price']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- Quick Quote / Cost Estimator Advanced Modal -->
<div class="modal" id="quickQuoteModal" style="display:none;">
    <div class="modal-content quick-quote-advanced">
        <span class="close-modal" id="closeQuickQuote">&times;</span>
        <!-- Progress Steps -->
        <div class="quote-progress">
            <div class="step active" data-step="1">Route</div>
            <div class="step" data-step="2">Details</div>
            <div class="step" data-step="3">Options</div>
        </div>
        <!-- Step 1: Route Selection -->
        <div class="quote-step" id="step1">
            <h2>Select Your Route</h2>
            <div class="route-inputs">
                <div class="input-group">
                    <label for="fromLocation">From</label>
                    <select name="from" id="fromLocation" required>
                        <option value="">Select starting point</option>
                        <?php
                        $locations = $conn->query("SELECT DISTINCT route_from FROM routes")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($locations as $loc) {
                            echo '<option value="'.htmlspecialchars($loc).'">'.htmlspecialchars($loc).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="input-group">
                    <label for="toLocation">To</label>
                    <select name="to" id="toLocation" required>
                        <option value="">Select destination</option>
                        <?php
                        $locations_to = $conn->query("SELECT DISTINCT route_to FROM routes")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($locations_to as $loc) {
                            echo '<option value="'.htmlspecialchars($loc).'">'.htmlspecialchars($loc).'</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <button class="next-step">Continue</button>
        </div>
        <!-- Step 2: Journey Details -->
        <div class="quote-step" id="step2" style="display:none;">
            <h2>Journey Details</h2>
            <div class="journey-inputs">
                <div class="input-group">
                    <label for="journeyDate">Date</label>
                    <input type="date" id="journeyDate" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="input-group">
                    <label for="passengers">Passengers</label>
                    <input type="number" id="passengers" min="1" max="8" value="1">
                </div>
            </div>
            <div class="journey-summary">
                <div class="summary-item">
                    <span id="distanceText">Calculating...</span>
                </div>
                <div class="summary-item">
                    <span id="durationText">Calculating...</span>
                </div>
                <div class="summary-item">
                    <span id="trafficText">Checking...</span>
                </div>
            </div>
            <div class="alternative-routes">
                <h3>Alternative Routes</h3>
                <div id="alternativesList"></div>
            </div>
            <div class="step-buttons">
                <button class="prev-step">Back</button>
                <button class="next-step">Continue</button>
            </div>
        </div>
        <!-- Step 3: Car Options -->
        <div class="quote-step" id="step3" style="display:none;">
            <h2>Choose Your Vehicle</h2>
            <div class="car-categories">
                <button class="category-btn active" data-category="all">All</button>
                <button class="category-btn" data-category="economy">Economy</button>
                <button class="category-btn" data-category="standard">Standard</button>
                <button class="category-btn" data-category="luxury">Luxury</button>
            </div>
            <div id="carsGrid" class="cars-grid"></div>
            <div class="deals-section">
                <h3>Available Deals</h3>
                <div id="dealsList"></div>
            </div>
            <div class="step-buttons">
                <button class="prev-step">Back</button>
                <button id="proceedToBooking" class="proceed-btn" style="display:none;">
                    Proceed to Booking
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Advanced Quick Quote Modal Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Modal handling
    const discoverBtn = document.querySelector('.cta-btn');
    const quickQuoteModal = document.getElementById('quickQuoteModal');
    const closeQuickQuote = document.getElementById('closeQuickQuote');
    let currentStep = 1;

    function showStep(step) {
        document.querySelectorAll('.quote-step').forEach((s, i) => {
            s.style.display = (i + 1 === step) ? 'block' : 'none';
        });
        document.querySelectorAll('.quote-progress .step').forEach((el, i) => {
            el.classList.toggle('active', i + 1 === step);
        });
        currentStep = step;
    }

    if (discoverBtn && quickQuoteModal) {
        discoverBtn.addEventListener('click', function(e) {
            e.preventDefault();
            quickQuoteModal.style.display = 'flex';
            showStep(1);
        });
    }
    if (closeQuickQuote && quickQuoteModal) {
        closeQuickQuote.onclick = function() {
            quickQuoteModal.style.display = 'none';
        };
    }
    window.addEventListener('click', function(event) {
        if (event.target === quickQuoteModal) {
            quickQuoteModal.style.display = 'none';
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && quickQuoteModal.style.display === 'flex') {
            quickQuoteModal.style.display = 'none';
        }
    });

    // Step navigation
    document.querySelectorAll('.next-step').forEach(btn => {
        btn.onclick = function() {
            if (currentStep < 3) showStep(currentStep + 1);
        };
    });
    document.querySelectorAll('.prev-step').forEach(btn => {
        btn.onclick = function() {
            if (currentStep > 1) showStep(currentStep - 1);
        };
    });

    // Advanced Quote AJAX Integration
    // Step 1 trigger: after choosing locations and clicking Continue, load route/journey details
    const fromLocation = document.getElementById('fromLocation');
    const toLocation = document.getElementById('toLocation');
    const journeyDate = document.getElementById('journeyDate');
    const passengers = document.getElementById('passengers');
    const distanceText = document.getElementById('distanceText');
    const durationText = document.getElementById('durationText');
    const trafficText = document.getElementById('trafficText');
    const alternativesList = document.getElementById('alternativesList');
    const carsGrid = document.getElementById('carsGrid');
    const dealsList = document.getElementById('dealsList');
    const proceedBtn = document.getElementById('proceedToBooking');

    let selectedRouteId = null;
    let selectedCategory = "all";

    function fetchJourneyAndAlternatives() {
        // Fetch advanced quote backend data
        const from = fromLocation.value;
        const to = toLocation.value;
        const date = journeyDate.value;
        const pax = passengers.value;
        if (!from || !to) return;

        fetch(`quick-quote-advanced.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&date=${encodeURIComponent(date)}&passengers=${encodeURIComponent(pax)}`)
        .then(res => res.json())
        .then(data => {
            // Journey summary
            distanceText.textContent = `${data.distance} km`;
            durationText.textContent = `${data.duration} hrs`;
            trafficText.textContent = data.traffic_condition || 'Normal';
            selectedRouteId = data.route_id;

            // Alternatives
            alternativesList.innerHTML = '';
            data.alternatives.forEach(alt => {
                const div = document.createElement('div');
                div.className = 'route-option';
                div.innerHTML = `<strong>${alt.route_name}</strong> (${alt.distance} km, ${alt.duration} hrs, ৳${alt.price}, ${alt.traffic_condition})`;
                div.onclick = function() {
                    selectedRouteId = alt.route_id;
                    distanceText.textContent = `${alt.distance} km`;
                    durationText.textContent = `${alt.duration} hrs`;
                    trafficText.textContent = alt.traffic_condition || 'Normal';
                    carsGrid.innerHTML = '';
                    dealsList.innerHTML = '';
                };
                alternativesList.appendChild(div);
            });
        });
    }

    // Step 2: show journey data when locations/date/passengers are set
    fromLocation.addEventListener('change', fetchJourneyAndAlternatives);
    toLocation.addEventListener('change', fetchJourneyAndAlternatives);
    journeyDate.addEventListener('change', fetchJourneyAndAlternatives);
    passengers.addEventListener('change', fetchJourneyAndAlternatives);

    // Step 3: After next-step from details, load cars and deals
    document.querySelector("#step2 .next-step").onclick = function() {
        showStep(3);
        fetchAvailableCarsDeals(selectedRouteId, selectedCategory);
    };

    function fetchAvailableCarsDeals(routeId, category) {
        if (!routeId) return;
        carsGrid.innerHTML = '<span>Loading vehicles...</span>';
        dealsList.innerHTML = '';
        fetch(`quick-quote-advanced.php?route_id=${encodeURIComponent(routeId)}&category=${encodeURIComponent(category)}`)
        .then(res => res.json())
        .then(data => {
            // Cars
            carsGrid.innerHTML = '';
            (data.cars || []).forEach(car => {
                const card = document.createElement('div');
                card.className = 'car-card';
                card.innerHTML = `<img src="${car.image}" alt="${car.model}">
                    <div class="car-card-content">
                        <h3><a href="car-detail.php?id=${car.id}">${car.model}</a></h3>
                        <p>৳${car.price}/day</p>
                        <button class="reserve-btn" data-car-id="${car.id}">Reserve Now</button>
                    </div>`;
                carsGrid.appendChild(card);
            });
            // Deals
            dealsList.innerHTML = '';
            (data.deals || []).forEach(deal => {
                const dealDiv = document.createElement('div');
                dealDiv.className = 'deal-card';
                dealDiv.innerHTML = `<strong>${deal.title}</strong>: ${deal.details}`;
                dealsList.appendChild(dealDiv);
            });
            proceedBtn.style.display = data.cars && data.cars.length ? 'inline-block' : 'none';
        });
    }

    // Car category filter (Step 3)
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedCategory = btn.getAttribute('data-category');
            fetchAvailableCarsDeals(selectedRouteId, selectedCategory);
        });
    });

    // Proceed to booking
    if (proceedBtn) {
        proceedBtn.onclick = function() {
            // Use selected car data or redirect to booking, etc.
            alert('Proceeding to booking flow...');
        };
    }

    // Fleet booking button standard logic
    document.querySelectorAll('.reserve-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const carId = btn.getAttribute('data-car-id');
            window.location.href = `booking.php?car_id=${encodeURIComponent(carId)}`;
        });
    });
});
</script>
</body>
</html>
