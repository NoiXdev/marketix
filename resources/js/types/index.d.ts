export interface User {
  id: number;
  name: string;
  email: string;
  super_admin: boolean;
}

export interface Project {
  id: number;
  name: string;
  locked: boolean;
}

export interface Domain {
  id: number;
  name: string;
  redirect_root: string | null;
  redirect_not_found: string | null;
  created_at: string;
  updated_at: string;
}

export interface Pixel {
  id: number;
  provider: string;
  name: string;
  tag: string;
  created_at?: string;
}

export type PixelOption = Pick<Pixel, 'id' | 'name' | 'provider'>;

export type ProjectRole = 'admin' | 'member';

export interface ProjectMember {
  id: number;
  name: string;
  email: string;
  role: ProjectRole;
}

export interface ProjectInvitation {
  id: number;
  email: string;
  role: ProjectRole;
  expires_at: string;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  };
  projects: Project[];
  project?: Project;
  currentProjectRole?: ProjectRole | null;
  flash: {
    success?: string;
    error?: string;
    warning?: string;
  };
};
