<?php

namespace App\Tests\DTO;

use App\DTO\AbstractRequestDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\HttpFoundation\Request;

class AbstractRequestDTOTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testFromArrayPopulatesProperties(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
            'active' => true,
        ];

        $dto = TestDTO::fromArray($data, $this->validator);

        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertTrue($dto->active);
    }

    public function testFromRequestParsesJsonBody(): void
    {
        $json = json_encode([
            'name' => 'Jane Smith',
            'age' => 25,
            'active' => false,
        ]);

        $request = new Request([], [], [], [], [], [], $json);
        $dto = TestDTO::fromRequest($request, $this->validator);

        $this->assertEquals('Jane Smith', $dto->name);
        $this->assertEquals(25, $dto->age);
        $this->assertFalse($dto->active);
    }

    public function testValidationFailsWhenRequiredFieldMissing(): void
    {
        $this->expectException(ValidationFailedException::class);

        $data = [
            'age' => 30,
            // 'name' is required but missing
        ];

        TestDTO::fromArray($data, $this->validator);
    }

    public function testValidationFailsWhenValueInvalid(): void
    {
        $this->expectException(ValidationFailedException::class);

        $data = [
            'name' => 'John Doe',
            'age' => -5, // Must be positive
        ];

        TestDTO::fromArray($data, $this->validator);
    }

    public function testTypeConversionWorksCorrectly(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => '30', // String should be converted to int
            'active' => '1', // String should be converted to bool
        ];

        $dto = TestDTO::fromArray($data, $this->validator);

        $this->assertIsInt($dto->age);
        $this->assertEquals(30, $dto->age);
        $this->assertIsBool($dto->active);
        $this->assertTrue($dto->active);
    }

    public function testToArrayReturnsCorrectData(): void
    {
        $data = [
            'name' => 'John Doe',
            'age' => 30,
            'active' => true,
        ];

        $dto = TestDTO::fromArray($data, $this->validator);
        $array = $dto->toArray();

        $this->assertEquals($data['name'], $array['name']);
        $this->assertEquals($data['age'], $array['age']);
        $this->assertEquals($data['active'], $array['active']);
    }
}

/**
 * Test DTO class for testing AbstractRequestDTO
 */
class TestDTO extends AbstractRequestDTO
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(min: 3, max: 50)]
    public string $name;

    #[Assert\Positive(message: 'Age must be positive')]
    public int $age;

    public bool $active = false;
}
