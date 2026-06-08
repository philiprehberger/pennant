# SDK roadmap

What's in v1, what's planned for v2, and the rules for adding a new implementation.

## v1 (shipped)

| Language | Package | Registry | Corpus runner |
| --- | --- | --- | --- |
| TypeScript (Node + browser core) | `@philiprehberger/pennant` | npm | Vitest |
| React | `@philiprehberger/pennant-react` | npm | Vitest |
| PHP / Laravel | `philiprehberger/pennant` | Packagist | PHPUnit |
| Python / Django / FastAPI | `pennant` | PyPI | pytest |

Each ships its own implementation of `bucketing` + `evaluator` + `client`. Each runs the cross-implementation drift corpus at `tests/corpus/rules.json` (repo root) in its native test runner. **Any divergence between implementations corrupts customer experiment data silently** — the corpus is the contract.

## v2 (planned, gated on buyer signal)

| Language | Package | Registry | Notes |
| --- | --- | --- | --- |
| Vue 3 composable | `@philiprehberger/pennant-vue` | npm | Sits on top of the TS core like the React adapter. Useful for Nuxt 3 stacks. |
| Svelte store | `@philiprehberger/pennant-svelte` | npm | Reactive store wrapper. |
| Go | `github.com/philiprehberger/pennant-go` | Go modules | Server-side. Idiomatic `Bool(ctx, "key", false)`. |
| Ruby / Rails | `pennant` | RubyGems | Server-side + Rails initializer. |
| Java / Kotlin | `com.philiprehberger:pennant` | Maven Central | JVM coverage. |
| Swift / iOS | git tag-resolved | SwiftPM | Mobile SDK with `URLCache` persistence. |
| .NET / C# | `Philiprehberger.Pennant` | NuGet | Microsoft stacks. |

## Adding a new implementation

1. Pick a language. Drop a sibling directory under `sdks/<lang>/`.
2. Port `bucketing` first. Confirm `tests/corpus/rules.json` → `bucketing.cases` round-trips to 1e-10 precision.
3. Port the `evaluator` — same operator set, same combinators, same depth limit (32), same dotted attribute lookup.
4. Run the rule-engine cases. **If any case fails, fix the implementation — never change the corpus to match an implementation.**
5. Port the `client` — bootstrap snapshot, in-memory cache, `pn_clt_` pre-evaluated values vs `pn_srv_` raw rules, refresh interval, optional persistent cache.
6. Add framework integrations as relevant to the language ecosystem.
7. Add a CI job that runs the corpus in the language's native test runner.

The smallest passing port is bucketing + evaluator + client + corpus test. Everything else is incremental.

## Constraints that apply to every implementation

- **Same hash function:** `sha256(flag_key + ":" + identifier + ":" + seed)`, take first 8 hex chars, divide by `0xffffffff`.
- **Same string concatenation:** UTF-8, colon-separated, no escaping.
- **Same operator names:** `equals`, `not_equals`, `in`, `not_in`, `contains`, `not_contains`, `starts_with`, `ends_with`, `regex`, `gt`, `gte`, `lt`, `lte`, `before`, `after`, `percentage`.
- **Same numeric semantics:** int and float are interchangeable for `equals`/`gt`/`lt`/etc. Boolean is *not* numeric.
- **Same dotted-attribute lookup:** `address.country` walks the context map.
- **Same depth limit:** 32. Beyond that, return `false` (treats cycles as no-match instead of stack-overflowing).
- **Same `none` semantics:** returns `true` when the children list is empty, otherwise `not any(child)`.
- **Same reason labels:** `default`, `off`, `rule_match`, `percentage_rollout`, `fallthrough`.

If any of these constraints needs to change, update this file, the corpus, and every existing implementation in the same PR. There is no v3 of the bucketing function — there is only a forked corpus that is the new contract.
