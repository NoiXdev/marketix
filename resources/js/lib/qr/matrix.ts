import qrcode from 'qrcode-generator';
import type { QrEcc } from '@/data/qrTypes';

export interface QrMatrix {
  size: number;
  isDark(row: number, col: number): boolean;
  isFinder(row: number, col: number): boolean;
}

export function buildMatrix(data: string, ecc: QrEcc): QrMatrix {
  const qr = qrcode(0, ecc); // 0 = auto version
  qr.addData(data || ' '); // never empty
  qr.make();
  const size = qr.getModuleCount();
  const inRange = (r: number, c: number) => r >= 0 && c >= 0 && r < size && c < size;
  const inBox = (r: number, c: number, r0: number, c0: number) =>
    r >= r0 && r < r0 + 7 && c >= c0 && c < c0 + 7;
  return {
    size,
    isDark: (r, c) => inRange(r, c) && qr.isDark(r, c),
    isFinder: (r, c) =>
      inBox(r, c, 0, 0) || inBox(r, c, 0, size - 7) || inBox(r, c, size - 7, 0),
  };
}
