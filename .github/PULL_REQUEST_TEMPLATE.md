## Summary

<!-- What does this change do, and why? Link any related issue: Closes #123 -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / internal change
- [ ] Documentation
- [ ] Test-only

## Checklist

- [ ] `./vendor/bin/pint --test` passes (no style violations)
- [ ] `php artisan test` passes (full Pest suite green; new behavior has tests in this PR)
- [ ] Commits follow Conventional Commits with short imperative subjects, one discrete change each
- [ ] Any schema change is a **new** migration (no edits to applied migrations); `$fillable`/`$casts`
      ship in the same commit
- [ ] No new runtime dependency (or it is called out and justified below)
- [ ] No secrets, alert-channel URLs, or monitor URLs added to logs, code, or the public status pages
- [ ] User input is validated server-side in a Form Request; user-rendered output stays escaped
- [ ] Docs updated where relevant (`README.md`, `docs/`); `docs/memory.md` notes any non-obvious decision

## Notes for reviewers

<!-- Concurrency-sensitive area touched? New dependency? Manual QA performed (real SMTP/Slack,
     scheduler/worker run)? Anything the CI gates cannot catch. -->
