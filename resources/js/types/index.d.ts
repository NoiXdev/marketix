export interface User {
  id: string;
  name: string;
  email: string;
  super_admin: boolean;
}

export interface Project {
  id: string;
  name: string;
  locked: boolean;
}

export interface DomainCheckDetails {
  dns?: { domain_ips?: string[]; app_ips?: string[]; error?: string };
  ssl?: { expires_at?: number; names?: string[]; error?: string };
  reachable?: { status?: number; error?: string };
}

export interface Domain {
  id: string;
  name: string;
  redirect_root: string | null;
  redirect_not_found: string | null;
  status: 'healthy' | 'error' | 'pending';
  dns_ok: boolean | null;
  reachable_ok: boolean | null;
  ssl_ok: boolean | null;
  check_details: DomainCheckDetails | null;
  last_checked_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Pixel {
  id: string;
  provider: string;
  name: string;
  tag: string;
  created_at?: string;
}

export type PixelOption = Pick<Pixel, 'id' | 'name' | 'provider'>;

export type ProjectRole = 'admin' | 'member';

export interface ProjectMember {
  id: string;
  name: string;
  email: string;
  role: ProjectRole;
}

export interface ProjectInvitation {
  id: string;
  email: string;
  role: ProjectRole;
  expires_at: string;
  expired: boolean;
  can_resend: boolean;
}

export interface ActivityEntry {
  id: number;
  log_name: string;
  description: string;
  event: string | null;
  subject_type: string | null;
  causer: { id: string; name: string } | null;
  // Spatie v5 attribute diffs (old → new) from the attribute_changes column.
  changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> };
  // Manual custom data (role, email, ip, …) from the properties column.
  properties: Record<string, unknown>;
  created_at: string;
  project?: { id: string; name: string } | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  };
  projects: Project[];
  project?: Project;
  currentProjectRole?: ProjectRole | null;
  version: string;
  branding: {
    appName: string;
    logoLight: string | null;
    logoDark: string | null;
    favicon: string | null;
  };
  flash: {
    success?: string;
    error?: string;
    warning?: string;
  };
};
