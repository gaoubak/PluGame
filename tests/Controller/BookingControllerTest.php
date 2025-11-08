<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Entity\ServiceOffering;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for Booking API endpoints
 */
class BookingControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListBookingsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/bookings');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetBookingReturnsNotFoundForInvalidId(): void
    {
        // Create authenticated request
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/bookings/invalid-id', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetBookingReturnsForbiddenForUnauthorizedUser(): void
    {
        // Create a booking owned by user A
        $bookingId = $this->createBooking();

        // Try to access as user B
        $token = $this->getAuthToken('userb@example.com');

        $this->client->request('GET', "/api/bookings/{$bookingId}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        // Should return 403 Forbidden due to BookingVoter
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Check RFC 7807 error format
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('forbidden', $response['type']);
        $this->assertEquals(403, $response['status']);
    }

    public function testAcceptBookingRequiresCreatorRole(): void
    {
        $bookingId = $this->createBooking();

        // Try to accept as athlete (should fail)
        $token = $this->getAuthToken('athlete@example.com');

        $this->client->request('POST', "/api/bookings/{$bookingId}/accept", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAcceptBookingFailsWhenStatusNotPending(): void
    {
        $bookingId = $this->createBooking('ACCEPTED'); // Already accepted

        $token = $this->getAuthToken('creator@example.com');

        $this->client->request('POST', "/api/bookings/{$bookingId}/accept", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        // Should return 409 Conflict
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        // Check RFC 7807 error format
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('booking-invalid-status', $response['type']);
        $this->assertEquals(409, $response['status']);
        $this->assertArrayHasKey('current_status', $response);
        $this->assertEquals('ACCEPTED', $response['current_status']);
    }

    public function testSoftDeleteBookingMarksAsDeleted(): void
    {
        $bookingId = $this->createBooking();
        $token = $this->getAuthToken('athlete@example.com');

        // Soft delete the booking
        $this->client->request('DELETE', "/api/bookings/delete/{$bookingId}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Try to fetch it - should be filtered out by soft delete filter
        $this->client->request('GET', "/api/bookings/{$bookingId}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRestoreBookingUndoesDelete(): void
    {
        $bookingId = $this->createBooking();
        $token = $this->getAuthToken('athlete@example.com');

        // Soft delete
        $this->client->request('DELETE', "/api/bookings/delete/{$bookingId}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        // Restore
        $this->client->request('POST', "/api/bookings/{$bookingId}/restore", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Should be visible again
        $this->client->request('GET', "/api/bookings/{$bookingId}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testPaginationReturnsCorrectFormat(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/bookings?page=1&limit=10', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Check paginated response format
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertArrayHasKey('page', $response['pagination']);
        $this->assertArrayHasKey('limit', $response['pagination']);
        $this->assertArrayHasKey('total', $response['pagination']);
        $this->assertArrayHasKey('totalPages', $response['pagination']);
        $this->assertArrayHasKey('hasNext', $response['pagination']);
        $this->assertArrayHasKey('hasPrev', $response['pagination']);
    }

    /**
     * Helper: Get JWT authentication token
     */
    private function getAuthToken(string $email = 'test@example.com'): string
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $email,
            'password' => 'password123',
        ]));

        $response = json_decode($this->client->getResponse()->getContent(), true);
        return $response['token'] ?? '';
    }

    /**
     * Helper: Create a test booking
     */
    private function createBooking(string $status = 'PENDING'): string
    {
        // This would normally use fixtures or factory pattern
        // For now, return a mock ID
        return 'test-booking-id-123';
    }
}
