// Lightweight helper to keep API parity with the shadcn examples.
// The Unovis components accept strings, so we simply return undefined when a component is not required.
// This keeps template calls using `componentToString` from breaking while letting us lean on the built-in tooltip rendering.
export function componentToString(): undefined {
  return undefined
}
