<?php

namespace App\Entity;

use App\Repository\LogRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Response;

#[ORM\Table(name: 'logs')]
#[ORM\Entity(repositoryClass: LogRepository::class)]
class Log
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $request;

    #[ORM\Column(type: 'text')]
    private string $response;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $requestAt;

    #[ORM\Column(type: 'integer')]
    private int $statusCode;

    public function __construct(
        string $request,
        string $response,
        DateTimeInterface $requestAt,
        int $statusCode = Response::HTTP_OK,
    )
    {
        $this->request = $request;
        $this->response = $response;
        $this->requestAt = $requestAt;
        $this->statusCode = $statusCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getRequest(): string
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * @return DateTimeInterface
     */
    public function getRequestAt(): DateTimeInterface
    {
        return $this->requestAt;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setRequest(string $request): static
    {
        $this->request = $request;

        return $this;
    }

    public function setResponse(string $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function setRequestAt(\DateTimeInterface $requestAt): static
    {
        $this->requestAt = $requestAt;

        return $this;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }
}
