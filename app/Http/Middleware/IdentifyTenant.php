<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For FB webhooks verification (GET), bypass tenant check since it's global per app
        if ($request->isMethod('get') && $request->query('hub_mode') === 'subscribe') {
            return $next($request);
        }

        $tenant = null;

        // Try to identify from FB/IG Webhook payload
        if ($request->has('entry')) {
            $entries = $request->input('entry', []);
            foreach ($entries as $entry) {
                // Determine if it's IG or FB
                $isInstagram = isset($entry['id']) && $request->input('object') === 'instagram';
                $pageId = $entry['id'] ?? null;
                
                if ($pageId) {
                    if ($isInstagram) {
                        $tenant = Tenant::where('ig_account_id', $pageId)->where('is_active', true)->first();
                    } else {
                        $tenant = Tenant::where('fb_page_id', $pageId)->where('is_active', true)->first();
                    }
                    
                    if ($tenant) {
                        break;
                    }
                }
            }
        }

        // Fallback for HTML chat or direct API testing via Custom Header
        if (!$tenant && $request->hasHeader('X-Tenant-ID')) {
            $tenant = Tenant::where('id', $request->header('X-Tenant-ID'))->where('is_active', true)->first();
        }

        if (!$tenant) {
            Log::warning('[IdentifyTenant] No active tenant found for request.', [
                'ip' => $request->ip(),
                'payload_summary' => $request->except(['entry']),
            ]);
            
            // For webhooks, we must return 200 OK even if we drop it, otherwise Meta will retry indefinitely
            if ($request->is('api/webhook*')) {
                return response('EVENT_RECEIVED', 200);
            }
            
            return response()->json(['error' => 'Tenant not found or inactive.'], 404);
        }

        // Bind the tenant to the service container
        app()->instance(Tenant::class, $tenant);
        
        Log::info("[IdentifyTenant] Bound Tenant ID: {$tenant->id} ({$tenant->name})");

        return $next($request);
    }
}
