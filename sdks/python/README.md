# pennant — Python SDK

Python client for [Pennant](https://github.com/philiprehberger/pennant) — feature flag evaluation with deterministic local bucketing and snapshot bootstrap.

```bash
pip install pennant
```

## Quickstart

```python
from pennant import Pennant
import os

pennant = Pennant(
    api_base="https://api.pennant.philiprehberger.com",
    api_key=os.environ["PENNANT_KEY"],
    environment="prod",
    context={"userId": "alice", "plan": "enterprise"},
)

if pennant.bool("new-checkout-flow", False):
    ...

cta = pennant.string("hero-cta-copy", "Get started")
limits = pennant.json("plan-limits", {"uploads": 10})
```

## Django

```python
# settings.py
PENNANT = {
    "API_BASE": "https://api.pennant.philiprehberger.com",
    "API_KEY": os.environ["PENNANT_API_KEY"],
    "ENVIRONMENT": "prod",
}

MIDDLEWARE = [
    ...,
    "pennant.django.PennantMiddleware",
]

# views.py
from pennant.django import flag_required

@flag_required("admin-panel")
def admin_view(request):
    ...
```

## FastAPI

```python
from fastapi import FastAPI, Depends
from pennant.fastapi import pennant_dependency, flag_required

app = FastAPI()
get_pennant = pennant_dependency(API_BASE, API_KEY, "prod")

@app.get("/")
async def root(pennant=Depends(get_pennant)):
    return {"checkout": "new" if pennant.bool("new-checkout-flow", False) else "legacy"}

@app.get("/admin", dependencies=[Depends(flag_required("admin-panel"))])
async def admin():
    ...
```

## Drift corpus

`tests/test_corpus.py` runs the cross-implementation rule-engine corpus at the repo root (`tests/corpus/rules.json`). The same corpus runs against the Laravel server (PHPUnit), TypeScript SDK (Vitest), and PHP SDK (PHPUnit). Any divergence fails CI on all four.

## License

MIT
