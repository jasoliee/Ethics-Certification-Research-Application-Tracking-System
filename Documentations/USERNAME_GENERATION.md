# Username Generation

## Format

`UsernameGenerator` normalizes the institutional identifier and surname into lowercase ASCII segments separated by periods.

```text
KLD-STU-501 + Dela Cruz -> kld.stu.501.dela.cruz
```

Non-alphanumeric runs become one period. Leading/trailing periods are removed. The result is 6 to 30 characters. Fallback segments protect against empty normalized values.

## Collisions

The first account receives the base username. Later collisions append a readable numeric suffix while preserving the 30-character maximum.

```text
kld.emp.301.reyes.santos
kld.emp.301.reyes.santos2
```

Bulk preview reserves usernames within the current file, and confirmation regenerates each value inside the transaction. If the expected preview username is no longer available, confirmation stops and requires a fresh preview.

## Ownership

Clients cannot submit or override a username during account creation. Surname or institutional-ID correction uses a separate confirmed action, not an ordinary profile save. Username updates notify the account holder and do not change password or role permissions.
