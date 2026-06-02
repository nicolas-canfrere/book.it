---
paths:
  - "src/**/UI/Http/Controller/**/*Controller.php"
---

# Controller Rules

- Every `#[MapRequestPayload(acceptFormat: 'json')]` parameter must be accompanied by a `new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(...))` in the OA attribute of the same method.
- After adding or changing any route, run `make openapi` to regenerate `openapi.yaml`.
