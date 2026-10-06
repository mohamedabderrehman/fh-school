# Synthetic demonstration

Student signs into an academic-year record → teacher opens a permitted subject/class → grades or attendance are saved → administrator publishes announcements and manages accounts.

## Walkthrough

1. Sign in with one synthetic student and inspect grades/absence.
2. Sign in as a teacher, open a permitted class, and save a demo change.
3. Reject an unauthenticated AJAX action and a non-permitted class.
4. Publish a synthetic announcement as admin and restore the fixtures.

## Acceptance checklist

- [ ] PHP syntax and SQLite integrity
- [ ] Synthetic login and permission rejection
- [ ] Saved demo changes and fixture reset
- [ ] Missing database behavior

## Evidence discipline

Screenshots must come from the running application with synthetic records. Record the component, viewport and configuration. A storyboard is not a recorded walkthrough. Benchmark only generated data and include hardware, input size, configuration, elapsed time and cache conditions.

Local demonstration only for the first release. Legacy password storage and broader CSRF/input review remain relevant before using real records.
