export type FrontendTextMap = Record<string, string>;

export function createTextResolver(frontendTexts: FrontendTextMap | null | undefined) {
  return (key: string, fallback: string): string => {
    const value = frontendTexts?.[key];
    return typeof value === 'string' && value.length > 0 ? value : fallback;
  };
}
