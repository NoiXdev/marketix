import { QrStyle } from '@/data/qrTypes';

export interface QrTemplate {
  id: string;
  name: string;
  style: QrStyle;
}

// Templates are copy-on-apply presets: applying one seeds the editor, and a
// later edit of the template never reaches QR codes created from it.
export async function listQrTemplates(projectId: string): Promise<QrTemplate[]> {
  const res = await window.axios.get<{ templates: QrTemplate[] }>(
    route('app.project.qr-templates.list', { project: projectId }),
  );
  return res.data.templates;
}

export async function createQrTemplate(projectId: string, name: string, style: QrStyle): Promise<QrTemplate> {
  const res = await window.axios.post<{ template: QrTemplate }>(
    route('app.project.qr-templates.store', { project: projectId }),
    { name, style },
  );
  return res.data.template;
}

export async function updateQrTemplate(
  projectId: string,
  templateId: string,
  payload: { name: string; style?: QrStyle },
): Promise<QrTemplate> {
  const res = await window.axios.put<{ template: QrTemplate }>(
    route('app.project.qr-templates.update', { project: projectId, qrTemplate: templateId }),
    payload,
  );
  return res.data.template;
}

export async function deleteQrTemplate(projectId: string, templateId: string): Promise<void> {
  await window.axios.delete(
    route('app.project.qr-templates.destroy', { project: projectId, qrTemplate: templateId }),
  );
}
