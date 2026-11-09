
<?php
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../app/layout.php';

$u = require_role(['restaurant', 'event_organizer', 'admin']);
$errors = [];

$tz = new DateTimeZone('Asia/Dhaka');
$now = new DateTimeImmutable('now', $tz);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (
        $u['verification_status'] !== 'verified' &&
        $u['role'] !== 'admin'
    ) {
        $errors[] = 'Your account must be verified before posting food.';
    }

    $name = trim($_POST['food_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $qty = (float) ($_POST['quantity_kg'] ?? 0);
    $source = $_POST['source_type'] ?? '';

    $cooked = trim($_POST['cooked_time'] ?? '');
    $deadline = trim($_POST['safety_deadline'] ?? '');

    $eventName = trim($_POST['event_name'] ?? '');
    $eventType = trim($_POST['event_type'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');

    $isEvent = $u['role'] === 'event_organizer';

    $storage = trim($_POST['storage_condition'] ?? '');
    $allergens = trim($_POST['allergens'] ?? '');

    $storageMethod = $_POST['storage_method'] ?? 'unknown';

    $storageTemp = ($_POST['storage_temp_c'] ?? '') !== ''
        ? (float) $_POST['storage_temp_c']
        : null;

    $ambientTemp = ($_POST['ambient_temp_c'] ?? '') !== ''
        ? (float) $_POST['ambient_temp_c']
        : null;

    $roomMinutes = ($_POST['room_temp_minutes'] ?? '') !== ''
        ? (int) $_POST['room_temp_minutes']
        : null;

    $desc = trim($_POST['description'] ?? '');
    $address = trim($_POST['pickup_address'] ?? '');

    $checklist = isset($_POST['safety_confirmed']);

    /*
     * Parse datetime-local values in Bangladesh timezone.
     */
    $cookedDate = null;
    $deadlineDate = null;
    $eventDateTime = null;

    try {
        if ($cooked !== '') {
            $cookedDate = new DateTimeImmutable($cooked, $tz);
        }

        if ($deadline !== '') {
            $deadlineDate = new DateTimeImmutable($deadline, $tz);
        }

        if ($eventDate !== '') {
            $eventDateTime = new DateTimeImmutable($eventDate, $tz);
        }
    } catch (Exception $e) {
        $errors[] = 'Invalid date or time. Please select the date and time again.';
    }

    /*
     * Required-field validation.
     */
    if (
        text_length($name) < 2 ||
        !$category ||
        $qty <= 0 ||
        !$deadline ||
        !$storage ||
        !$address
    ) {
        $errors[] = 'Complete all required fields with valid values.';
    }

    /*
     * Event organizer validation.
     */
    if ($isEvent) {
        $source = 'event_leftover';

        if (
            text_length($eventName) < 2 ||
            !$eventType ||
            !$eventDateTime
        ) {
            $errors[] = 'Enter the event name, event type and event date.';
        }

        if (
            $eventDateTime &&
            $eventDateTime > $now->modify('+1 day')
        ) {
            $errors[] = 'Event date cannot be more than one day in the future.';
        }
    }

    /*
     * Validate food source.
     */
    if (
        !in_array(
            $source,
            [
                'cancelled_order',
                'extra_batch',
                'buffet_catering_leftover',
                'event_leftover'
            ],
            true
        )
    ) {
        $errors[] = 'Choose a valid surplus source.';
    }

    /*
     * Food safety confirmation.
     */
    if (!$checklist) {
        $errors[] = 'Confirm the food safety declaration.';
    }

    if (
        !in_array(
            $storageMethod,
            [
                'hot',
                'refrigerated',
                'room_temperature',
                'frozen',
                'unknown'
            ],
            true
        )
    ) {
        $errors[] = 'Choose a valid storage method.';
    }

    if ($roomMinutes !== null && $roomMinutes < 0) {
        $errors[] = 'Room-temperature minutes cannot be negative.';
    }

    /*
     * Date/time validation.
     */
    if ($deadlineDate && $deadlineDate <= $now) {
        $errors[] = 'Safe pickup deadline must be in the future.';
    }

    if ($cookedDate && $cookedDate > $now) {
        $errors[] = 'Cooked / prepared time cannot be in the future. Please check the selected date and time.';
    }

    /*
     * Upload optional food photo.
     */
    $photoPath = null;

    if (!$errors) {
        try {
            $photoPath = safe_upload('food_photo', 'food');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    /*
     * Insert food and related safety records.
     */
    if (!$errors) {
        try {
            $db = db();

            $deadlineSql = $deadlineDate->format('Y-m-d H:i:s');

            $cookedSql = $cookedDate
                ? $cookedDate->format('Y-m-d H:i:s')
                : null;

            $eventSql = ($isEvent && $eventDateTime)
                ? $eventDateTime->format('Y-m-d H:i:s')
                : null;

            $hoursRemaining = max(
                0,
                (strtotime($deadlineSql) - $now->getTimestamp()) / 3600
            );

            $score = max(
                1,
                min(100, 100 - (int) ($hoursRemaining * 2))
            );

            $db->beginTransaction();

            /*
             * Food remains available in the database but is not
             * eligible for rescue until safety review is approved.
             */
            $stmt = $db->prepare(
                "INSERT INTO surplus_food (
                    restaurant_id,
                    food_name,
                    category,
                    description,
                    quantity_kg,
                    source_type,
                    event_name,
                    event_type,
                    event_date,
                    cooked_time,
                    safety_deadline,
                    storage_condition,
                    allergens,
                    pickup_address,
                    photo,
                    priority_score,
                    status,
                    safety_declared,
                    storage_method,
                    storage_temp_c,
                    ambient_temp_c,
                    room_temp_minutes,
                    batch_token
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'available', 1, ?, ?, ?, ?, ?
                )"
            );

            $stmt->execute([
                $u['id'],
                $name,
                $category,
                $desc,
                $qty,
                $source,
                $isEvent ? $eventName : null,
                $isEvent ? $eventType : null,
                $eventSql,
                $cookedSql,
                $deadlineSql,
                $storage,
                $allergens,
                $address,
                $photoPath,
                $score,
                $storageMethod,
                $storageTemp,
                $ambientTemp,
                $roomMinutes,
                bin2hex(random_bytes(16))
            ]);

            $foodId = (int) $db->lastInsertId();

            $checkStmt = $db->prepare(
                "INSERT INTO food_safety_checks (
                    surplus_food_id,
                    checklist_result,
                    result,
                    reviewer_id
                ) VALUES (?, ?, ?, ?)"
            );

            $checkStmt->execute([
                $foodId,
                json_encode(
                    [
                        'safety_confirmed' => true,
                        'storage_condition' => $storage,
                        'allergens' => $allergens
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
                'pending',
                $u['id']
            ]);

            /*
             * Notify active administrators.
             */
            $notifyStmt = $db->prepare(
                "INSERT INTO notifications (user_id, title, message)
                 SELECT id, ?, ?
                 FROM users
                 WHERE is_active = 1 AND role = 'admin'"
            );

            $notifyStmt->execute([
                'Food listing needs safety review',
                $name . ' (#' . $foodId .
                ') was submitted by ' . $u['name'] .
                '. Review it in the admin review queue.'
            ]);

            $db->commit();

            flash(
                'Food listing submitted for admin safety review. It will appear in available food and smart matching only after approval.',
                'success'
            );

            redirect(
                $u['role'] === 'admin'
                    ? 'food.php'
                    : 'my_food.php'
            );

        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Food listing error: ' . $e->getMessage());

            $errors[] = 'Food could not be submitted. Please check the database setup and try again.';
        }
    }
}

page_header(
    $u['role'] === 'event_organizer'
        ? 'Post event surplus food'
        : 'Post surplus food'
);
?>

<div class="page-intro">
    <div class="eyebrow">
        <?= $u['role'] === 'event_organizer'
            ? 'EVENT ORGANIZER WORKSPACE'
            : 'FOOD DONOR WORKSPACE' ?>
    </div>

    <h1>Post surplus <em>food.</em></h1>

    <p>
        Donate safe extra food from a restaurant, wedding, conference,
        party or community event. Set a conservative safety deadline.
    </p>
</div>

<div class="form-panel">

    <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-grid" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <label>
            Food name
            <input
                name="food_name"
                required
                value="<?= e($_POST['food_name'] ?? '') ?>"
                placeholder="e.g. Vegetable khichuri"
            >
        </label>

        <label>
            Category
            <select name="category" required>
                <option value="">Select category</option>

                <?php foreach (
                    [
                        'Rice & Biryani',
                        'Curry & Vegetables',
                        'Bakery',
                        'Fruit',
                        'Packaged food',
                        'Snacks',
                        'Other'
                    ] as $c
                ): ?>
                    <option
                        value="<?= e($c) ?>"
                        <?= (($_POST['category'] ?? '') === $c)
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="full">
            Food photo (optional, JPG/PNG/WEBP, max 3 MB)
            <input
                type="file"
                name="food_photo"
                accept="image/jpeg,image/png,image/webp"
            >
        </label>

        <label>
            Quantity (kg)
            <input
                type="number"
                min="0.1"
                step="0.1"
                name="quantity_kg"
                required
                value="<?= e($_POST['quantity_kg'] ?? '') ?>"
            >
        </label>

        <?php if ($u['role'] === 'event_organizer'): ?>

            <label class="full">
                Event name
                <input
                    name="event_name"
                    required
                    value="<?= e($_POST['event_name'] ?? '') ?>"
                    placeholder="e.g. Community Iftar / Wedding reception"
                >
            </label>

            <label>
                Event type
                <select name="event_type" required>
                    <option value="">Select event type</option>

                    <?php foreach (
                        [
                            'Wedding',
                            'Conference',
                            'Birthday / private party',
                            'Community event',
                            'Religious event',
                            'Corporate event',
                            'Sports / cultural event',
                            'Other'
                        ] as $et
                    ): ?>
                        <option
                            value="<?= e($et) ?>"
                            <?= (($_POST['event_type'] ?? '') === $et)
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($et) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Event date &amp; time
                <input
                    type="datetime-local"
                    name="event_date"
                    required
                    value="<?= e($_POST['event_date'] ?? '') ?>"
                >
            </label>

            <input
                type="hidden"
                name="source_type"
                value="event_leftover"
            >

            <div class="notice-panel full">
                <strong>Event food donation</strong>
                <p>
                    List only food that has remained within safe time
                    and temperature controls. Event food is still
                    subject to admin safety review.
                </p>
            </div>

        <?php else: ?>

            <label>
                Surplus source
                <select name="source_type" required>
                    <option value="cancelled_order">Cancelled order</option>
                    <option value="extra_batch">Extra batch</option>
                    <option value="buffet_catering_leftover">
                        Buffet / catering leftover
                    </option>
                </select>
            </label>

        <?php endif; ?>

        <label>
            Cooked / prepared time
            <input
                type="datetime-local"
                name="cooked_time"
                value="<?= e($_POST['cooked_time'] ?? '') ?>"
            >
        </label>

        <label>
            Safe pickup deadline
            <input
                type="datetime-local"
                name="safety_deadline"
                required
                value="<?= e($_POST['safety_deadline'] ?? '') ?>"
            >
        </label>

        <label class="full">
            Storage condition
            <input
                name="storage_condition"
                required
                value="<?= e($_POST['storage_condition'] ?? '') ?>"
                placeholder="e.g. kept covered in a refrigerator"
            >
        </label>

        <label>
            Storage method
            <select name="storage_method">
                <?php
                $methods = [
                    'unknown' => 'Unknown / needs review',
                    'hot' => 'Hot-held',
                    'refrigerated' => 'Refrigerated',
                    'room_temperature' => 'Room temperature',
                    'frozen' => 'Frozen'
                ];
                ?>

                <?php foreach ($methods as $value => $label): ?>
                    <option
                        value="<?= e($value) ?>"
                        <?= (($_POST['storage_method'] ?? 'unknown') === $value)
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Measured food temperature (°C)
            <input
                type="number"
                step="0.1"
                name="storage_temp_c"
                value="<?= e($_POST['storage_temp_c'] ?? '') ?>"
                placeholder="Measured value, if known"
            >
        </label>

        <label>
            Ambient temperature (°C)
            <input
                type="number"
                step="0.1"
                name="ambient_temp_c"
                value="<?= e($_POST['ambient_temp_c'] ?? '') ?>"
                placeholder="If known"
            >
        </label>

        <label>
            Room-temperature exposure (minutes)
            <input
                type="number"
                min="0"
                step="1"
                name="room_temp_minutes"
                value="<?= e($_POST['room_temp_minutes'] ?? '') ?>"
                placeholder="If known"
            >
        </label>

        <label class="full">
            Allergens (if known)
            <input
                name="allergens"
                value="<?= e($_POST['allergens'] ?? '') ?>"
                placeholder="e.g. peanuts, dairy, wheat; write 'unknown' if not known"
            >
        </label>

        <label class="full">
            Pickup address in Bangladesh
            <input
                name="pickup_address"
                required
                value="<?= e($_POST['pickup_address'] ?? $u['address'] ?? '') ?>"
                placeholder="Area, Thana, District"
            >
        </label>

        <label class="full">
            Description
            <textarea
                name="description"
                rows="3"
                placeholder="Quantity details, packaging, pickup instructions..."
            ><?= e($_POST['description'] ?? '') ?></textarea>
        </label>

        <label class="check-row full">
            <input
                type="checkbox"
                name="safety_confirmed"
                required
                <?= isset($_POST['safety_confirmed']) ? 'checked' : '' ?>
            >
            I confirm this food is suitable for donation, has been
            handled safely, and the information above is accurate.
        </label>

        <div class="full">
            <button class="btn" type="submit">
                Submit food listing ↗
            </button>

            <a class="btn btn-light" href="<?= e(url('dashboard.php')) ?>">
                Cancel
            </a>
        </div>

    </form>
</div>

<?php page_footer(); ?>
