<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use App\Services\FlagEvaluator;
use App\Services\RuleEvaluator;
use App\Services\SegmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'environment' => ['required', 'string'],
            'context' => ['required', 'array'],
            'flags' => ['nullable', 'array'],
            'flags.*' => ['string'],
        ])->validate();

        /** @var \App\Models\Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $env = $workspace->environments()->where('key', $data['environment'])->firstOrFail();

        $flagQuery = $workspace->flags()->whereNull('archived_at');
        if (! empty($data['flags'])) {
            $flagQuery->whereIn('key', $data['flags']);
        }
        /** @var iterable<Flag> $flags */
        $flags = $flagQuery->get();

        $evaluator = new FlagEvaluator(new RuleEvaluator((new SegmentResolver($workspace))->asCallable()));

        $out = [];
        foreach ($flags as $flag) {
            $out[$flag->key] = $evaluator->evaluate($flag, $env, $data['context']);
        }

        return response()->json($out);
    }
}
