# Security Policy

## Supported Scope

This project processes identity-document images and should be treated as sensitive.

## Reporting a Vulnerability

If you discover a security issue, do not open a public issue with exploit details.

Please report privately to the repository owner/maintainer with:

- Summary of the issue
- Reproduction steps
- Affected files/endpoints
- Potential impact

## Sensitive Data Rules

- Never commit real NID images to git.
- Never commit `.env` or credentials.
- Sanitize logs before sharing.
- Use synthetic or explicitly consented sample data for demos/tests.

## Hardening Checklist

- Run with HTTPS in deployment
- Restrict upload size and mime types
- Keep dependencies patched
- Add auth/rate limiting before public exposure
- Use encrypted storage for uploaded files
