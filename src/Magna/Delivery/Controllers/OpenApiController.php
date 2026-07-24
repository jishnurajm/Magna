<?php

declare(strict_types=1);

namespace Magna\Delivery\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Magna\Delivery\OpenApiGenerator;

final class OpenApiController extends Controller
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->generator->generate());
    }
}
