# System Resilience & Health Probes

Covers the Circuit Breaker pattern for third-party API protection and Health/Readiness endpoints for container orchestration.

**Source files:** `src/Services/CircuitBreaker.php`, `src/Controllers/HealthController.php`

---

## Circuit Breaker (`CircuitBreaker`)

Prevents cascading system failures when external third-party services (payment gateways, external webhooks, email relays) experience outages.

```
       ┌────────────────────────┐
       │         CLOSED         │  Normal state: calls pass through
       └────────────────────────┘
          │                 ▲
 5 failures │                 │ Success in HALF_OPEN
          ▼                 │
       ┌────────────────────────┐
       │          OPEN          │  Service failing: calls fail fast
       └────────────────────────┘
          │
Recovery  │
timeout   ▼
       ┌────────────────────────┐
       │       HALF_OPEN        │  Trial state: test if service recovered
       └────────────────────────┘
```

### Circuit States

| State | Behavior |
|-------|----------|
| `CLOSED` | Normal operation. All calls pass through to target service. Failure count tracked. |
| `OPEN` | Threshold reached (default 5 failures). Calls fail immediately or return fallback without attempting network connection. |
| `HALF_OPEN` | Recovery timeout elapsed (default 60s). Allows limited trial calls to test service health. Success returns state to `CLOSED`. |

### Usage

```php
use App\Services\CircuitBreaker;

$cb = new CircuitBreaker(
    service: 'payment_gateway',
    failureThreshold: 5,        // Open after 5 consecutive failures
    recoveryTimeout: 60,        // Try recovery after 60 seconds
    halfOpenMaxAttempts: 2      // 2 test calls in half-open state
);

$result = $cb->call(
    operation: function() use ($amount) {
        return PaymentGateway::charge($amount);
    },
    fallback: function() {
        // Return fallback response when circuit is OPEN
        return ['status' => 'degraded', 'message' => 'Payment service temporarily unavailable'];
    }
);
```

State persistence is maintained per service in `/storage/cache/circuit_breaker/`.

---

## Health & Readiness Probes (`HealthController`)

Provides standard JSON health endpoints for Kubernetes, Docker Healthcheck, or load balancer probes.

### `GET /health` — Health Probe

Checks basic application status and dependency connectivity (Database, Redis).

**Response (Healthy - HTTP 200):**
```json
{
    "status": "success",
    "data": {
        "status": "healthy",
        "checks": {
            "database": true,
            "redis": true
        },
        "timestamp": "2024-01-15T14:30:00+00:00"
    }
}
```

**Response (Degraded - HTTP 503):**
```json
{
    "status": "success",
    "data": {
        "status": "degraded",
        "checks": {
            "database": false,
            "redis": true
        },
        "timestamp": "2024-01-15T14:30:00+00:00"
    }
}
```

### `GET /ready` — Readiness Probe

Simple probe verifying database readiness before receiving traffic.

- **HTTP 200**: Database reachable, ready for requests
- **HTTP 503**: Database unreachable, remove container from load balancer pool

---

## Docker Healthcheck Integration

In `docker-compose.yml`:

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 10s
  timeout: 5s
  retries: 3
```
