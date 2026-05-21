# UI archetype standards

Three top-level UI archetypes carry the product. Each has ONE canonical
reference and ONE shared scaffold component. New screens of an archetype MUST
use the shared scaffold; the reference is the visual bar ("the bare minimum of
how it should look and function"). The `Phase74UiArchetypeTest` guards this.

| Archetype | Reference page | Shared scaffold | Use it for |
|-----------|----------------|-----------------|------------|
| **Hub** | `Pages/Finances/Index.vue` | `Components/Hub/HubShell.vue` | A workspace that groups related sub-views as tabs (Operations, Maintenance, Water, Archive, Tenants) |
| **Center** | `Pages/Notifications/Index.vue` | `Components/Center/CenterHero.vue` | A top-level command surface with a gradient masthead + a primary action (Notification Center, Legal-Hold Command Center) |
| **Wizard** | `Pages/Onboarding/Index.vue` + `Pages/Notifications/components/SetupWizard.vue` | `Components/Wizard/WizardSteps.vue` | A guided multi-step flow (registration, notification setup, legal-hold) |

---

## Hub — `HubShell`

A hub is the chrome around a set of tabs. `HubShell` owns: the `Head` title
(`"<Title> - <Tab>"`), the `AuthenticatedLayout` `#header` (an accent
icon-in-box + title + subtitle), a `Breadcrumb`, and a white `rounded-xl`
card holding the underline tab bar. The hub page supplies the tab list +
accent + the active tab component in the default slot.

```vue
<HubShell title="Maintenance" subtitle="Tickets and complaints"
  :icon="WrenchScrewdriverIcon" accent="orange"
  route-name="maintenance.hub" :tabs="tabs" :current-tab="currentTab">
  <component :is="currentTabComponent" ... />
</HubShell>
```

Checklist (all provided by HubShell — don't re-implement):
- Icon-in-box header (accent colour) + title + subtitle.
- Breadcrumb (`Hub > Tab`, overridable via `breadcrumb` prop).
- Underline tab bar in a white card; active tab = accent border + text + icon.
- Hover-`prefetch` + `?tab=` navigation with `preserveState`/`preserveScroll`.
- Per-tab badge counts via `tab.badge`.
- Accents: `emerald` (Finance), `purple` (Operations), `orange` (Maintenance),
  `cyan` (Water), `gray` (Archive), `blue` (Tenants). Add to the `ACCENTS`
  map in HubShell (static classes — Tailwind purges dynamic strings).

The reference (`Finances/Index.vue`) additionally has grouped **sub-tabs** + a
Pinia store + modals; HubShell covers the common shell. A hub that needs
sub-tabs follows the Finances pattern.

## Center — `CenterHero`

A center leads with a full-width indigo→purple gradient masthead: an icon in a
`white/20` box, a large bold title, a subtitle, and a right-aligned primary
action (`#action` slot — typically a wizard launch). Below the hero, a center
shows pill-style tab nav (Notification Center) or a command dashboard
(Legal-Hold Command Center).

```vue
<CenterHero title="Notification Center" subtitle="..." :icon="BellAlertIcon">
  <template #action><button ...>Setup Wizard</button></template>
</CenterHero>
```

## Wizard — `WizardSteps`

A wizard shows progress with the shared `WizardSteps`: the indigo→purple
gradient progress bar + a `Step X of N` label, plus a named-step pill rail when
`steps` labels are passed. Below it: the current step's content, then a footer
with **Back** (left) and **Continue / Complete** (right).

```vue
<WizardSteps :current-step="step + 1" :total-steps="STEPS.length" :steps="stepLabels" />
```

- The onboarding `WizardProgressBar` delegates to `WizardSteps` (keeps its
  onboarding label via the `label` prop).
- The notification `SetupWizard` is the modal variant (same gradient bar in a
  `Teleport` dialog with a stepped header + Back/Skip/Continue/Complete footer).
- Shared label keys live in `lang/*/common.php` (`common.wizard.*`).

---

## Adding a new screen

- **New hub** → render `<HubShell>`; register an accent in its `ACCENTS` map.
- **New center** → lead with `<CenterHero>`.
- **New wizard** → render `<WizardSteps>` above the step content; use the
  Back/Continue/Complete footer.

`tests/Feature/Phase74UiArchetypeTest.php` asserts every `*/Hub.vue`
(except the Finances reference) imports `HubShell`, both centers import
`CenterHero`, and the page wizards import `WizardSteps`.
