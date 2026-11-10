<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../database/app/helpers.php';

final class HelpersTest extends TestCase
{
    public function testHtmlEscaping(): void
    {
        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        self::assertSame('', e(null));
    }

    public function testUnicodeTextLength(): void
    {
        self::assertSame(5, text_length('বাংলা'));
        self::assertSame(5, text_length('Hello'));
        self::assertSame(0, text_length(''));
    }

    public function testUrlGeneration(): void
    {
        self::assertSame(rtrim(BASE_URL, '/') . '/login.php', url('login.php'));
        self::assertSame(rtrim(BASE_URL, '/') . '/', url());
    }

    public function testClientIp(): void
    {
        $original = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            self::assertSame('127.0.0.1', client_ip());

            unset($_SERVER['REMOTE_ADDR']);
            self::assertSame('', client_ip());
        } finally {
            if ($original === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $original;
            }
        }
    }

    public function testStatusBadge(): void
    {
        self::assertSame('<span class="badge good">Verified</span>', status_badge('verified'));
        self::assertSame('<span class="badge warn">In Transit</span>', status_badge('in_transit'));
        self::assertSame('<span class="badge bad">Rejected</span>', status_badge('rejected'));
        self::assertSame('<span class="badge neutral">Unknown</span>', status_badge('unknown'));
    }

    public function testFoodExpiry(): void
    {
        self::assertTrue(is_expired(['safety_deadline' => date('Y-m-d H:i:s', time() - 3600)]));
        self::assertFalse(is_expired(['safety_deadline' => date('Y-m-d H:i:s', time() + 3600)]));
    }

    public function testBangladeshPhoneValidation(): void
    {
        self::assertTrue(bangladesh_phone_valid('01712345678'));
        self::assertTrue(bangladesh_phone_valid('+8801712345678'));
        self::assertTrue(bangladesh_phone_valid('017-1234-5678'));

        self::assertFalse(bangladesh_phone_valid('01212345678'));
        self::assertFalse(bangladesh_phone_valid('12345'));
        self::assertFalse(bangladesh_phone_valid('0171234567'));
    }

    public function testFoodImageUrl(): void
    {
        self::assertStringEndsWith('/assets/food/fruit.svg', food_image_url(['category' => 'Fruit']));

        self::assertStringEndsWith(
            '/uploads/test.jpg',
            food_image_url(['photo' => 'uploads/test.jpg', 'category' => 'Fruit'])
        );
    }

    public function testCsrfTokenGenerationAndReuse(): void
    {
        $original = $_SESSION['csrf_token'] ?? null;

        try {
            unset($_SESSION['csrf_token']);

            $token = csrf_token();

            self::assertSame(64, strlen($token));
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
            self::assertSame($token, csrf_token());
        } finally {
            if ($original === null) {
                unset($_SESSION['csrf_token']);
            } else {
                $_SESSION['csrf_token'] = $original;
            }
        }
    }

    public function testFlashMessageLifecycle(): void
    {
        $original = $_SESSION['flash'] ?? null;

        try {
            flash('Test notification', 'success');

            self::assertSame(
                ['message' => 'Test notification', 'type' => 'success'],
                $_SESSION['flash']
            );

            self::assertSame(
                ['message' => 'Test notification', 'type' => 'success'],
                take_flash()
            );

            self::assertNull(take_flash());
        } finally {
            if ($original === null) {
                unset($_SESSION['flash']);
            } else {
                $_SESSION['flash'] = $original;
            }
        }
    }

    public function testStatusBadgeIsCaseInsensitive(): void
    {
        self::assertSame(
            '<span class="badge good">VERIFIED</span>',
            status_badge('VERIFIED')
        );

        self::assertSame(
            '<span class="badge warn">Under Review</span>',
            status_badge('under_review')
        );

        self::assertSame(
            '<span class="badge bad">Cancelled</span>',
            status_badge('cancelled')
        );
    }

    public function testFoodImageCategoryMappings(): void
    {
        $categories = [
            'Rice & Biryani' => 'rice',
            'Curry & Vegetables' => 'curry',
            'Bakery' => 'bakery',
            'Fruit' => 'fruit',
            'Packaged food' => 'packaged',
            'Snacks' => 'snacks',
        ];

        foreach ($categories as $category => $image) {
            self::assertStringEndsWith(
                '/assets/food/' . $image . '.svg',
                food_image_url(['category' => $category])
            );
        }
    }

    public function testUnknownFoodCategoryUsesOtherImage(): void
    {
        self::assertStringEndsWith(
            '/assets/food/other.svg',
            food_image_url(['category' => 'Unknown Category'])
        );

        self::assertStringEndsWith(
            '/assets/food/other.svg',
            food_image_url([])
        );
    }

    public function testFoodExpiryWithPastAndFutureDates(): void
    {
        $past = ['safety_deadline' => date('Y-m-d H:i:s', time() - 86400)];
        $future = ['safety_deadline' => date('Y-m-d H:i:s', time() + 86400)];

        self::assertTrue(is_expired($past));
        self::assertFalse(is_expired($future));
    }
    public function testCurrentUserReturnsNullWithoutSessionUser(): void
    {
        $original = $_SESSION['user'] ?? null;

        try {
            unset($_SESSION['user']);

            self::assertNull(current_user());
        } finally {
            if ($original === null) {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user'] = $original;
            }
        }
    }

    public function testSafeUploadReturnsNullWhenNoFileProvided(): void
    {
        $original = $_FILES['test_upload'] ?? null;

        try {
            unset($_FILES['test_upload']);

            self::assertNull(safe_upload('test_upload'));
        } finally {
            if ($original === null) {
                unset($_FILES['test_upload']);
            } else {
                $_FILES['test_upload'] = $original;
            }
        }
    }

    public function testSafeDocumentUploadReturnsNullWhenNoFileProvided(): void
    {
        $original = $_FILES['test_document'] ?? null;

        try {
            unset($_FILES['test_document']);

            self::assertNull(safe_document_upload('test_document'));
        } finally {
            if ($original === null) {
                unset($_FILES['test_document']);
            } else {
                $_FILES['test_document'] = $original;
            }
        }
    }
}
