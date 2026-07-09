<?php

declare(strict_types=1);

namespace App\UI\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -64],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($event->getResponse() !== null) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/') && !str_starts_with($path, '/internal/')) {
            return;
        }

        $throwable = $event->getThrowable();
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
        }

        $message = $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Erro interno.';

        $event->setResponse(new JsonResponse([
            'message' => $message,
            'code' => 'INTERNAL_ERROR',
        ], $status));
    }
}
