export default function AboutPage() {
  return (
    <div className="prose pb-20">
      <h1 className="text-3xl font-bold tracking-tight">About this demo</h1>

      <p>
        Pennant is a portfolio demonstration by{' '}
        <a href="https://philiprehberger.com">Philip Rehberger</a>. It is not a
        production service. Don't point real production traffic at it; the live
        demo's API key minting and sandbox isolation are intentionally relaxed
        so you can read the wire format end-to-end without registration friction.
      </p>

      <h2>Why this exists</h2>
      <p>
        Most engineering portfolios stop at the README. A buyer evaluating a
        freelancer for an "API product" engagement is being asked to extrapolate
        from "built a thing on GitHub" to "can deliver a production API with
        docs, SDKs, and a deploy story to my team's standards." The
        extrapolation is expensive, and most freelancers don't shorten it.
        Pennant — and its sibling project{' '}
        <a href="https://github.com/philiprehberger/webhook-relay">webhook-relay</a>{' '}
        — exist to close that gap.
      </p>
      <p>
        Where webhook-relay sells to API teams (HMAC signing, retries,
        dead-letter queues), Pennant is shaped for product teams: targeting
        rules, percentage rollouts, kill switches, audit trails, real-time SDK
        updates.
      </p>

      <h2>What's "production-shaped" mean?</h2>
      <p>
        The architecture is the architecture a real flag service would use, and
        the SDK is built the way a real flag SDK gets built — synchronous reads
        that never throw, deterministic bucketing, offline-resilient cache,
        graceful degradation when the SSE connection drops. But this is one
        person working on a portfolio. There is no on-call rotation, no
        five-nines SLA, no SOC2 audit. "Production-shaped, not production-grade"
        is the honest framing.
      </p>

      <h2>What I'd build for you</h2>
      <p>
        If your team is debating "buy vs build" on a flag service, or you have
        an API project that needs the whole product surface delivered, the
        artifacts on this site are the kind of thing you'd be paying for.
        Contact me at{' '}
        <a href="https://philiprehberger.com">philiprehberger.com</a> or via{' '}
        <a href="https://www.upwork.com/freelancers/philiprehberger">Upwork</a>.
      </p>
    </div>
  );
}
