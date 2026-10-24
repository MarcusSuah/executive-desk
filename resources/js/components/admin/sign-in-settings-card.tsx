import { router, useForm } from '@inertiajs/react';
import { Check, Copy, ImageUp, KeyRound, RotateCcw } from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { loginBackground, signIn as saveSignIn } from '@/routes/admin/settings';
import { destroy as removeBackground } from '@/routes/admin/settings/login-background';

export type SignInSettings = {
    google_client_id: string;
    google_hosted_domain: string;
    google_secret_saved: boolean;
    google_enabled: boolean;
    google_from_environment: boolean;
    google_redirect_uri: string;
    background_url: string | null;
    background_uploaded: boolean;
};

/**
 * Lets administrators turn on Google sign-in and choose the sign-in background.
 */
export function SignInSettingsCard({ settings }: { settings: SignInSettings }) {
    const [copiedText, copy] = useClipboard();
    const fileInput = useRef<HTMLInputElement>(null);

    const google = useForm({
        google_client_id: settings.google_client_id,
        google_client_secret: '',
        google_hosted_domain: settings.google_hosted_domain,
    });

    const background = useForm<{ background: File | null }>({
        background: null,
    });

    const submitGoogle = (event: FormEvent) => {
        event.preventDefault();
        google.put(saveSignIn.url(), {
            preserveScroll: true,
            onSuccess: () => google.setData('google_client_secret', ''),
        });
    };

    const uploadBackground = (event: FormEvent) => {
        event.preventDefault();
        background.post(loginBackground.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                background.reset();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Sign-in page</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-8 xl:grid-cols-2">
                <form onSubmit={submitGoogle} className="space-y-4">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                            <KeyRound className="size-4" aria-hidden />
                            Sign in with Google
                        </h3>
                        <StatusBadge
                            status={
                                settings.google_enabled ? 'active' : 'inactive'
                            }
                            label={settings.google_enabled ? 'On' : 'Off'}
                        />
                    </div>
                    <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>
                            In the Google Cloud console, open{' '}
                            <a
                                href="https://console.cloud.google.com/apis/credentials"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-foreground underline underline-offset-4"
                            >
                                APIs &amp; Services → Credentials
                            </a>{' '}
                            and create an <strong>OAuth client ID</strong> of
                            type <strong>Web application</strong>.
                        </li>
                        <li>Add this authorized redirect URI:</li>
                    </ol>
                    <div className="flex gap-2">
                        <Input
                            readOnly
                            value={settings.google_redirect_uri}
                            aria-label="Authorized redirect URI"
                            className="font-mono text-xs"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() =>
                                void copy(settings.google_redirect_uri)
                            }
                            aria-label="Copy redirect URI"
                        >
                            {copiedText === settings.google_redirect_uri ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        3. Paste the client ID and secret below.
                    </p>

                    <FormField
                        id="google_client_id"
                        label="Client ID"
                        error={google.errors.google_client_id}
                    >
                        <Input
                            id="google_client_id"
                            value={google.data.google_client_id}
                            onChange={(e) =>
                                google.setData(
                                    'google_client_id',
                                    e.target.value,
                                )
                            }
                            placeholder="1234567890-abc.apps.googleusercontent.com"
                            autoComplete="off"
                        />
                    </FormField>
                    <FormField
                        id="google_client_secret"
                        label="Client secret"
                        error={google.errors.google_client_secret}
                        hint={
                            settings.google_secret_saved
                                ? 'A secret is saved (stored encrypted). Leave blank to keep it.'
                                : 'Stored encrypted. It is never shown again after saving.'
                        }
                    >
                        <Input
                            id="google_client_secret"
                            type="password"
                            value={google.data.google_client_secret}
                            onChange={(e) =>
                                google.setData(
                                    'google_client_secret',
                                    e.target.value,
                                )
                            }
                            placeholder={
                                settings.google_secret_saved
                                    ? '••••••••••••'
                                    : ''
                            }
                            autoComplete="new-password"
                        />
                    </FormField>
                    <FormField
                        id="google_hosted_domain"
                        label="Only allow this Google Workspace domain (optional)"
                        error={google.errors.google_hosted_domain}
                    >
                        <Input
                            id="google_hosted_domain"
                            value={google.data.google_hosted_domain}
                            onChange={(e) =>
                                google.setData(
                                    'google_hosted_domain',
                                    e.target.value,
                                )
                            }
                            placeholder="yourcompany.com"
                        />
                    </FormField>
                    <p className="text-xs text-muted-foreground">
                        Google sign-in never creates accounts. People must
                        already have an account here with the same,
                        Google-verified email address.
                        {settings.google_from_environment &&
                            ' Values saved here take precedence over the GOOGLE_* settings in .env.'}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={google.processing}>
                            {google.processing && <Spinner />}
                            Save Google sign-in
                        </Button>
                        {(settings.google_client_id ||
                            settings.google_secret_saved) && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    router.put(
                                        saveSignIn.url(),
                                        { clear: true },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                </form>

                <form onSubmit={uploadBackground} className="space-y-4">
                    <h3 className="flex items-center gap-2 text-sm font-semibold">
                        <ImageUp className="size-4" aria-hidden />
                        Background image
                    </h3>
                    <div
                        className="relative aspect-[16/10] overflow-hidden rounded-lg border bg-[#071029]"
                        style={
                            settings.background_url
                                ? {
                                      backgroundImage: `linear-gradient(90deg, rgba(7,16,41,0.9), rgba(7,16,41,0.3)), url(${settings.background_url})`,
                                      backgroundSize: 'cover',
                                      backgroundPosition: 'center',
                                  }
                                : undefined
                        }
                        role="img"
                        aria-label="Preview of the sign-in background"
                    >
                        {!settings.background_url && (
                            <div className="absolute inset-0 bg-gradient-to-br from-[#0b1a3d] via-[#071029] to-[#030712]" />
                        )}
                        <div className="absolute bottom-3 left-3 text-sm font-semibold text-white">
                            {settings.background_url
                                ? 'Current background'
                                : 'Default background'}
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            aria-label="Background image"
                            onChange={(e) =>
                                background.setData(
                                    'background',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        {background.progress && (
                            <progress
                                value={background.progress.percentage}
                                max={100}
                                className="h-1 w-full"
                            />
                        )}
                        <InputError message={background.errors.background} />
                        <p className="text-xs text-muted-foreground">
                            JPG, PNG, or WebP up to 5 MB, at least 800 × 600
                            pixels. A wide photo (1600 px or more) looks best;
                            the left side is darkened so the text stays
                            readable.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            disabled={
                                background.processing ||
                                !background.data.background
                            }
                        >
                            {background.processing && <Spinner />}
                            Upload background
                        </Button>
                        {settings.background_uploaded && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    router.delete(removeBackground.url(), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <RotateCcw />
                                Use default
                            </Button>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
