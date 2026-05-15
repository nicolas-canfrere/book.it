<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\ViolationsCarrierInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
final readonly class ProblemDetailExceptionListener
{
    public function __construct(private ExceptionProblemRegistry $registry)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $mapping = $this->registry->resolve($exception);
        if (null !== $mapping) {
            $problem = new ProblemDetail(
                type: $mapping['type'],
                title: $mapping['title'],
                status: $mapping['status'],
                detail: $exception->getMessage(),
                violations: $exception instanceof ViolationsCarrierInterface ? $exception->getViolations() : null,
            );
            $event->setResponse($this->toResponse($problem));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse($this->toResponse($this->fromHttpException($exception)));

            return;
        }

        $event->setResponse($this->toResponse(new ProblemDetail(
            type: 'about:blank',
            title: 'Internal Server Error',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
            detail: 'An unexpected error occurred.',
        )));
    }

    private function fromHttpException(HttpExceptionInterface $exception): ProblemDetail
    {
        $status = $exception->getStatusCode();
        $title = Response::$statusTexts[$status] ?? 'Error';
        $previous = $exception->getPrevious();

        if (Response::HTTP_UNPROCESSABLE_ENTITY === $status && $previous instanceof ValidationFailedException) {
            $violations = [];
            foreach ($previous->getViolations() as $violation) {
                $violations[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            return new ProblemDetail(
                type: 'about:blank',
                title: 'Unprocessable Content',
                status: $status,
                detail: 'Validation failed.',
                violations: $violations,
            );
        }

        return new ProblemDetail(
            type: 'about:blank',
            title: $title,
            status: $status,
            detail: $exception->getMessage() ?: $title,
        );
    }

    private function toResponse(ProblemDetail $problem): JsonResponse
    {
        return new JsonResponse(
            $problem->toArray(),
            $problem->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
