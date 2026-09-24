# design.md — MarketLink

## Color & Theme
Farm-fresh, natural feel — greens and warm earth tones, avoid a generic
corporate blue look.

| Role | Color | Hex |
|---|---|---|
| Primary | Fresh Green | #3D8B47 |
| Primary Dark (hover/active) | Deep Green | #2A6334 |
| Accent | Warm Harvest Orange | #E8935A |
| Background | Off-white / Cream | #FBF9F4 |
| Surface (cards) | White | #FFFFFF |
| Text — Primary | Charcoal | #2B2B28 |
| Text — Muted | Warm Gray | #6E6A62 |
| Success | #3D8B47 (reuse primary) |
| Warning | #E8935A (reuse accent) |
| Error | #C0392B |

Dark mode (optional, matching Fan Hub Plus/Campus Coin accessibility asks
if you choose to add it later): invert background/surface to dark
charcoal (#1E1F1C / #262722), keep the greens slightly brighter for
contrast (#4CAF58).

## Fonts
- **Headings:** "Poppins" (Google Fonts) — friendly, rounded, modern
- **Body text:** "Inter" or "Nunito Sans" — clean and highly readable at
  small sizes for product lists/tables
- Fallback stack: `'Poppins', 'Segoe UI', sans-serif` /
  `'Inter', 'Segoe UI', sans-serif`

## Typography scale
| Element | Size | Weight |
|---|---|---|
| Page title (H1) | 28–32px | 700 |
| Section heading (H2) | 22–24px | 600 |
| Card title (H3) | 18px | 600 |
| Body text | 15–16px | 400 |
| Small/meta text (dates, tags) | 13px | 400 |
| Buttons | 15px | 600 |

## UI notes
- Product cards: image on top, name + price bold, farmer name muted below
- Status badges (order status) use color-coding: placed = gray, accepted
  = blue, ready = orange/accent, completed = green, cancelled = red
- Map markers: green pin for markets, orange pin for farmer stalls
- Keep spacing generous — this is a browse-and-discover app, avoid
  cramming too much per screen
