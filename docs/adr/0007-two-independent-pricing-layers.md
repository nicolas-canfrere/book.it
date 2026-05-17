# ADR-0007 — Two independent pricing layers: Rate Periods and Promotions

Pricing uses two independent, non-overlapping-within-themselves layers: Rate Periods (explicit per-night prices that override the Base Rate) and Promotions (percentage discounts applied on top of the current applicable price). A Promotion may overlap any Rate Period — the two layers are orthogonal.

The alternative was a single layer of pricing rules with a "promotional" flag. We rejected it because Rate Periods and Promotions serve distinct commercial purposes: Rate Periods express market pricing (high/low season), Promotions express tactical discounts on top of that market price. Conflating them into one layer would force operators to re-enter the full price every time they want to add a discount, losing the semantic distinction between "what the room costs" and "what we're offering right now."

Overlap within a layer (two Rate Periods or two Promotions covering the same night) is forbidden to avoid ambiguity. Overlap between layers is intentional and expected.
