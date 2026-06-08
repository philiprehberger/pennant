'use client';

import { useMemo, useState } from 'react';
import { bucket } from '@philiprehberger/pennant';

export default function BucketingPage() {
  const [flagKey, setFlagKey] = useState('new-checkout');
  const [seed, setSeed] = useState('01HF3W0AXNVZ');
  const [percentage, setPercentage] = useState(25);
  const [userCount, setUserCount] = useState(1000);

  const stats = useMemo(() => {
    const out: { user: string; bucket: number; inRollout: boolean }[] = [];
    let hits = 0;
    for (let i = 0; i < userCount; i++) {
      const id = `user-${i}`;
      const b = bucket(flagKey, id, seed);
      const inRollout = b < percentage / 100;
      if (inRollout) hits++;
      if (i < 50) out.push({ user: id, bucket: b, inRollout });
    }
    const actualPct = userCount === 0 ? 0 : (hits / userCount) * 100;
    return { sample: out, hits, actualPct };
  }, [flagKey, seed, percentage, userCount]);

  return (
    <div className="space-y-8 pb-20">
      <header>
        <h1 className="text-3xl font-bold tracking-tight">Bucketing visualizer</h1>
        <p className="mt-3 max-w-3xl text-(--color-ink-dim)">
          Percentage rollouts have to be <em>deterministic</em>: the same user lands in the same bucket on every page refresh, every device, every server. Pennant uses <code>sha256(flagKey + ":" + userId + ":" + seed)[0..8] / 0xffffffff</code>. Both the Laravel server and the TypeScript SDK ship the same implementation; the corpus enforces parity.
        </p>
      </header>

      <section className="grid gap-4 rounded-lg border border-(--color-paper-dim) bg-white p-5 md:grid-cols-2 lg:grid-cols-4">
        <Field label="Flag key" value={flagKey} onChange={setFlagKey} />
        <Field label="Bucketing seed" value={seed} onChange={setSeed} />
        <NumberField label="Rollout %" value={percentage} onChange={setPercentage} min={0} max={100} />
        <NumberField label="Sample users" value={userCount} onChange={setUserCount} min={1} max={50000} />
      </section>

      <section className="rounded-lg border border-(--color-paper-dim) bg-white p-5">
        <div className="flex items-baseline gap-6">
          <Stat label="Rollout target" value={`${percentage.toFixed(1)}%`} />
          <Stat label="Actual hits" value={stats.hits.toLocaleString()} />
          <Stat label="Actual %" value={`${stats.actualPct.toFixed(2)}%`} />
        </div>
        <p className="mt-4 text-sm text-(--color-ink-dim)">
          Distribution falls within ±2% of the target at 1000+ users. The first 50 bucket placements are shown below for inspection.
        </p>
      </section>

      <section className="overflow-hidden rounded-lg border border-(--color-paper-dim)">
        <table className="w-full text-sm">
          <thead className="bg-(--color-paper-dim) text-left text-xs uppercase tracking-wider text-(--color-ink-dim)">
            <tr>
              <th className="px-4 py-2">User</th>
              <th className="px-4 py-2">Bucket</th>
              <th className="px-4 py-2">In rollout</th>
              <th className="px-4 py-2">Position</th>
            </tr>
          </thead>
          <tbody>
            {stats.sample.map(({ user, bucket: b, inRollout }) => (
              <tr key={user} className="border-t border-(--color-paper-dim) bg-white">
                <td className="px-4 py-1.5 font-mono text-xs">{user}</td>
                <td className="px-4 py-1.5 font-mono text-xs">{b.toFixed(8)}</td>
                <td className="px-4 py-1.5">
                  <span
                    className={
                      inRollout
                        ? 'rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700'
                        : 'rounded bg-(--color-paper-dim) px-2 py-0.5 text-xs font-medium text-(--color-ink-dim)'
                    }
                  >
                    {inRollout ? 'yes' : 'no'}
                  </span>
                </td>
                <td className="px-4 py-1.5">
                  <div className="h-2 w-full overflow-hidden rounded bg-(--color-paper-dim)">
                    <div
                      className="h-full bg-(--color-accent)"
                      style={{ width: `${b * 100}%` }}
                    />
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}

function Field({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wider text-(--color-ink-dim)">
      {label}
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="rounded border border-(--color-paper-dim) bg-(--color-paper) px-3 py-2 font-mono text-sm text-(--color-ink) normal-case tracking-normal"
      />
    </label>
  );
}

function NumberField({
  label,
  value,
  onChange,
  min,
  max,
}: {
  label: string;
  value: number;
  onChange: (v: number) => void;
  min: number;
  max: number;
}) {
  return (
    <label className="flex flex-col gap-1 text-xs font-semibold uppercase tracking-wider text-(--color-ink-dim)">
      {label}
      <input
        type="number"
        min={min}
        max={max}
        value={value}
        onChange={(e) => onChange(Number(e.target.value) || 0)}
        className="rounded border border-(--color-paper-dim) bg-(--color-paper) px-3 py-2 font-mono text-sm text-(--color-ink) normal-case tracking-normal"
      />
    </label>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wider text-(--color-ink-dim)">{label}</div>
      <div className="mt-1 text-2xl font-bold text-(--color-ink)">{value}</div>
    </div>
  );
}
