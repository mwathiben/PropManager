# DPIA Process — When a Data Protection Impact Assessment is Required

Phase-13 DPA-7: Kenya DPA Section 31 / GDPR Article 35 require a Data Protection Impact Assessment (DPIA) before any *high-risk* processing activity. PropManager has the `KenyaDpaService::generateDpiaTemplate()` helper but no process forcing its use for new features.

## The gate

**A DPIA is required when a PR introduces or expands processing in any of these areas:**

- Any of the SENSITIVE_DATA_CATEGORIES (defined in `KenyaDpaService::SENSITIVE_DATA_CATEGORIES`):
  - `national_id`, `ethnic_origin`, `health_data`
  - `biometric_data`, `genetic_data`
  - `sex_life`, `sexual_orientation`
  - `political_opinion`, `religious_belief`
  - `trade_union_membership`, `criminal_record`
- Large-scale processing (>10,000 data subjects or >1,000 sensitive-category subjects)
- Automated decision-making affecting tenants (eviction scoring, automated late-fee assessment without override)
- Combining datasets in a way that creates a new lawful-basis question (e.g., merging KYC photos with location data)
- Cross-border transfer to a non-adequate-protection destination (see `KenyaDpaService::canTransferCrossBorder`)

## PR checklist

Add this section to PR descriptions when introducing any of the above:

```markdown
## DPIA

- [ ] This PR touches one of the DPIA-required areas above
- [ ] DPIA generated via `php artisan tinker` → `app(\App\Services\KenyaDpaService::class)->generateDpiaTemplate('<activity>')`
- [ ] DPIA attached as `docs/legal/dpia/<feature>.md` in this PR
- [ ] Lawful basis declared on the affected models via `getLawfulBasis()` (DPA-3)
- [ ] Retention period documented in `getRetentionRequirements()` if a new category
- [ ] Cross-border transfer assessed via `canTransferCrossBorder()` (DPA-2)
- [ ] Privacy notice template updated (DPA-9) if disclosure changes
- [ ] Sign-off from CISO / Legal recorded in PR

OR

- [ ] This PR does NOT touch DPIA-required areas.
```

## CI helper (optional)

A grep-based CI warning can catch sensitive-data references in new code:

```yaml
# .github/workflows/ci.yml — optional warning step
- name: Warn on SENSITIVE_DATA_CATEGORIES references
  run: |
    git diff origin/main..HEAD -- 'app/' 'database/' | grep -iE \
      'national_id|ethnic_origin|health_data|biometric|genetic|sex_life|sexual_orientation|political_opinion|religious_belief|trade_union|criminal_record' \
      && echo "WARN: sensitive-data category touched. DPIA required (docs/legal/dpia-process.md)" || true
```

This warns without failing — the gate is the PR-template checkbox; the grep is the reminder.

## DPIA archive

Each completed DPIA lives at `docs/legal/dpia/<feature-slug>.md` and is the authoritative record. Format follows the `generateDpiaTemplate` output:

1. Description of processing
2. Lawful basis assessment
3. Necessity and proportionality
4. Risk assessment + matrix
5. Risk mitigation measures
6. Consultation (subjects, ODPC, stakeholders)
7. Sign-off

Reference each DPIA from `docs/legal/dpia/INDEX.md` (create as needed).
