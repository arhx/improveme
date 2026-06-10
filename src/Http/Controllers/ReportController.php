<?php

namespace Arhx\Improveme\Http\Controllers;

use Arhx\Improveme\Reporting\ReportData;
use Arhx\Improveme\Reporting\ReportDispatcher;
use Arhx\Improveme\Reporting\ScreenshotStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Receives a feedback report from the widget, stores its screenshot and fans
 * it out to the configured channels.
 *
 * Extend this class (or point config('improveme.controller') at your own) to
 * customise validation, persistence or routing of reports.
 */
class ReportController
{
    public function __invoke(Request $request): JsonResponse
    {
        $config = config('improveme');

        if (! ($config['enabled'] ?? true)) {
            return response()->json(['ok' => false], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($config));
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $report = ReportData::fromRequest($request);

        $report->screenshotPath = (new ScreenshotStore($config['storage'] ?? []))
            ->store($request->input('screenshot'));

        $this->dispatcher($config)->dispatch($report);

        return response()->json(['ok' => true]);
    }

    protected function rules(array $config): array
    {
        $maxHtml = (int) ($config['widget']['max_html'] ?? 20000);

        return [
            'type' => ['nullable', 'in:bug,idea'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'page.url' => ['nullable', 'string', 'max:2048'],
            'url' => ['nullable', 'string', 'max:2048'],
            'element.selector' => ['nullable', 'string', 'max:2048'],
            'element.tag' => ['nullable', 'string', 'max:64'],
            'element.html' => ['nullable', 'string', 'max:'.max($maxHtml + 2000, 22000)],
            'screenshot' => ['nullable', 'string'],
            'consoleErrors' => ['nullable', 'array', 'max:50'],
            'consoleErrors.*.level' => ['nullable', 'string', 'max:16'],
            'consoleErrors.*.text' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function dispatcher(array $config): ReportDispatcher
    {
        return new ReportDispatcher($config);
    }
}
