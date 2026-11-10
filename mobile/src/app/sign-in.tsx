import { useQueryClient } from '@tanstack/react-query';
import * as AppleAuthentication from 'expo-apple-authentication';
import * as Linking from 'expo-linking';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ComponentRef, ReactNode, useEffect, useRef, useState } from 'react';
import { Alert, KeyboardAvoidingView, Modal, Platform, Pressable, TextInput, TextInputProps, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import { useTranslation } from 'react-i18next';
import { api, ApiError } from '../api/client';
import { useConfig } from '../api/hooks';
import type { AuthResponse, DeviceLimitError } from '../api/types';
import { Brand } from '../components/Brand';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { Screen } from '../components/Screen';
import { Text } from '../components/Text';
import { deviceInfo } from '../lib/device';
import { completeSignIn } from '../lib/session';
import { fonts, radius, useTheme } from '../theme';

type Mode = 'signin' | 'signup';
type Step = 'form' | 'verify' | 'forgot' | 'reset';
type CodeSent = { masked: string; resend_in: number; debug_code?: string };

const isEmail = (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim());
const strongEnough = (v: string) => v.length >= 8 && /[A-Za-z]/.test(v) && /\d/.test(v);

/** 19 · Sign in / create account — email + password (code by email), Telegram, Apple (iOS) or guest. */
export default function SignIn() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const config = useConfig();
  const params = useLocalSearchParams<{ mode?: string }>();

  const [mode, setMode] = useState<Mode>(params.mode === 'signup' ? 'signup' : 'signin');
  const [step, setStep] = useState<Step>('form');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [sent, setSent] = useState<CodeSent | null>(null);
  const [resendIn, setResendIn] = useState(0);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [limit, setLimit] = useState<(DeviceLimitError & { retry: (id: number) => void }) | null>(null);
  const [appleAvailable, setAppleAvailable] = useState(false);
  const codeRef = useRef<ComponentRef<typeof TextInput>>(null);

  useEffect(() => {
    if (Platform.OS === 'ios') AppleAuthentication.isAvailableAsync().then(setAppleAvailable).catch(() => {});
  }, []);

  useEffect(() => {
    if (resendIn <= 0) return;
    const id = setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => clearTimeout(id);
  }, [resendIn]);

  const switchMode = (m: Mode) => {
    setMode(m);
    setStep('form');
    setError(null);
  };

  const message = (e: unknown) => (e instanceof ApiError ? e.message : t('common.error_body', 'Please try again.'));

  const finish = async (res: AuthResponse) => {
    const next = await completeSignIn(res, qc);
    router.replace(next);
  };

  /** Runs a sign-in request; on the 2-device limit, asks which device to sign out and retries. */
  const attempt = async (key: string, request: (extra: Record<string, unknown>) => Promise<AuthResponse>) => {
    setBusy(key);
    setError(null);
    try {
      await finish(await request({}));
    } catch (e) {
      if (e instanceof ApiError && e.kind === 'device_limit') {
        setLimit({
          ...(e.data as DeviceLimitError),
          retry: async (replaceId: number) => {
            setLimit(null);
            setBusy(key);
            try {
              await finish(await request({ replace_device_id: replaceId }));
            } catch (err) {
              setError(message(err));
            } finally {
              setBusy(null);
            }
          },
        });
      } else {
        setError(message(e));
      }
    } finally {
      setBusy(null);
    }
  };

  const codeSent = (res: CodeSent, next: Step) => {
    setSent(res);
    setResendIn(res.resend_in);
    setCode('');
    setStep(next);
    setTimeout(() => codeRef.current?.focus(), 250);
  };

  const request = async (key: string, fn: () => Promise<void>) => {
    setBusy(key);
    setError(null);
    try {
      await fn();
    } catch (e) {
      setError(message(e));
    } finally {
      setBusy(null);
    }
  };

  // ---- Email flows ----
  const signUp = () => request('signup', async () => codeSent(await api<CodeSent>('/auth/email/register', { body: { name: name.trim(), email: email.trim(), password } }), 'verify'));

  const verifySignUp = () =>
    attempt('verify', async (extra) => api<AuthResponse>('/auth/email/verify', { body: { email: email.trim(), code, ...(await deviceInfo()), ...extra } }));

  const signIn = () =>
    attempt('signin', async (extra) => api<AuthResponse>('/auth/email/login', { body: { email: email.trim(), password, ...(await deviceInfo()), ...extra } }));

  const forgot = () =>
    request('forgot', async () => {
      codeSent(await api<CodeSent>('/auth/email/forgot', { body: { email: email.trim() } }), 'reset');
      setPassword('');
    });

  const resetPassword = () =>
    attempt('reset', async (extra) => api<AuthResponse>('/auth/email/reset', { body: { email: email.trim(), code, password, ...(await deviceInfo()), ...extra } }));

  const resend = () =>
    request('resend', async () => {
      const res = await api<CodeSent>('/auth/email/resend', { body: { email: email.trim(), purpose: step === 'reset' ? 'reset' : 'signup' } });
      setSent(res);
      setResendIn(res.resend_in);
    });

  // ---- Other sign-in methods ----
  const telegram = async () => {
    if (!config.data?.auth.telegram_login) {
      return Alert.alert(t('signin.telegram_soon_title', 'Telegram login is not set up yet'), t('signin.telegram_soon', 'Use your email for now. Telegram login will be switched on soon.'));
    }
    // Telegram confirms in the browser (like "Sign in with Google") and returns a one-time ticket to the app.
    setError(null);
    setBusy('telegram');
    let ticket: string;
    try {
      const returnTo = Linking.createURL('auth/telegram');
      const url = `${config.data.auth.telegram_login_url}&return_to=${encodeURIComponent(returnTo)}`;
      const result = await WebBrowser.openAuthSessionAsync(url, returnTo);
      if (result.type !== 'success') return setBusy(null);
      const q = Linking.parse(result.url).queryParams ?? {};
      if (q.error || !q.ticket) {
        setBusy(null);
        return setError(String(q.error ?? t('signin.telegram_failed', 'Telegram login did not finish. Please try again.')));
      }
      ticket = String(q.ticket);
    } catch {
      setBusy(null);
      return setError(t('signin.telegram_failed', 'Telegram login did not finish. Please try again.'));
    }
    await attempt('telegram', async (extra) => api<AuthResponse>('/auth/telegram', { body: { ticket, ...(await deviceInfo()), ...extra } }));
  };

  const apple = () =>
    attempt('apple', async (extra) => {
      const cred = await AppleAuthentication.signInAsync({
        requestedScopes: [AppleAuthentication.AppleAuthenticationScope.FULL_NAME, AppleAuthentication.AppleAuthenticationScope.EMAIL],
      });
      const fullName = [cred.fullName?.givenName, cred.fullName?.familyName].filter(Boolean).join(' ') || undefined;
      return api<AuthResponse>('/auth/apple', { body: { id_token: cred.identityToken, name: fullName, ...(await deviceInfo()), ...extra } });
    });

  const guest = () => attempt('guest', async (extra) => api<AuthResponse>('/auth/guest', { body: { ...(await deviceInfo()), ...extra } }));

  const freeQuestions = config.data?.guest_free_questions ?? 20;

  const titles: Record<Step, [string, string]> = {
    form:
      mode === 'signup'
        ? [t('signin.signup_title', 'Create your free account'), t('signin.signup_sub_email', 'Takes a minute. We’ll email you a code to confirm it’s you.')]
        : [t('signin.title_email', 'Welcome back'), t('signin.sub_email', 'Sign in to continue where you left off.')],
    verify: [t('signin.verify_title', 'Check your email'), t('signin.verify_sub', 'Enter the 6-digit code we sent to {email}.', { email: sent?.masked ?? '' })],
    forgot: [t('signin.forgot_title', 'Forgot your password?'), t('signin.forgot_sub', 'Enter your email and we’ll send you a code to set a new one.')],
    reset: [t('signin.reset_title', 'Set a new password'), t('signin.reset_sub', 'If {email} has an account, a code is on its way.', { email: sent?.masked ?? '' })],
  };

  const back = step !== 'form' ? () => (setStep(step === 'reset' ? 'forgot' : 'form'), setError(null)) : null;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.page }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={{ backgroundColor: '#062A5E', paddingTop: insets.top + 16, paddingHorizontal: 24, paddingBottom: 24, gap: 14 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Brand size={44} />
          {back ? (
            <Pressable onPress={back} accessibilityRole="button" accessibilityLabel={t('common.back', 'Back')} hitSlop={10} style={{ width: 40, height: 40, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' }}>
              <Icon name="back" size={20} color="#F3F6FA" />
            </Pressable>
          ) : null}
        </View>
        <Text style={{ fontFamily: fonts.display, fontSize: 30, lineHeight: 34, letterSpacing: -0.8 }} color="#F3F6FA">
          {titles[step][0]}
        </Text>
        <Text variant="small" color="#A9BBD8">
          {titles[step][1]}
        </Text>
      </View>

      <Screen contentStyle={{ paddingTop: 20, gap: 14 }} bottomInset={18} background={c.page} offlineBar={false}>
        {step === 'form' ? (
          <>
            <View style={{ flexDirection: 'row', backgroundColor: c.chip, borderRadius: 14, padding: 4 }} accessibilityRole="tablist">
              {(['signin', 'signup'] as const).map((m) => (
                <Pressable
                  key={m}
                  onPress={() => switchMode(m)}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: mode === m }}
                  style={{ flex: 1, height: 40, borderRadius: 11, alignItems: 'center', justifyContent: 'center', backgroundColor: mode === m ? c.card : 'transparent' }}
                >
                  <Text style={{ fontFamily: mode === m ? fonts.bodySemibold : fonts.bodyMedium, fontSize: 14 }} color={mode === m ? c.text : c.textSoft}>
                    {m === 'signin' ? t('signin.tab_signin', 'Sign in') : t('signin.tab_signup', 'Create account')}
                  </Text>
                </Pressable>
              ))}
            </View>

            {mode === 'signup' ? (
              <Field label={t('signin.name', 'Full name')} icon="user" value={name} onChangeText={setName} autoCapitalize="words" autoComplete="name" textContentType="name" placeholder={t('signin.name_ph', 'e.g. Abebe Kebede')} />
            ) : null}
            <Field
              label={t('signin.email', 'Email')}
              icon="globe"
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              textContentType="emailAddress"
              placeholder="you@example.com"
            />
            <PasswordField
              label={t('signin.password', 'Password')}
              value={password}
              onChangeText={setPassword}
              isNew={mode === 'signup'}
              onSubmitEditing={mode === 'signin' ? signIn : signUp}
              hint={mode === 'signup' ? t('signin.password_hint', 'At least 8 characters, with letters and numbers.') : undefined}
            />

            {mode === 'signin' ? (
              <Pressable onPress={() => (setStep('forgot'), setError(null))} accessibilityRole="button" hitSlop={8} style={{ alignSelf: 'flex-end', marginTop: -4 }}>
                <Text variant="smallStrong" color={c.teal}>
                  {t('signin.forgot', 'Forgot password?')}
                </Text>
              </Pressable>
            ) : null}

            {mode === 'signin' ? (
              <Button title={t('signin.signin_btn', 'Sign in')} onPress={signIn} loading={busy === 'signin'} disabled={!isEmail(email) || !password} />
            ) : (
              <Button title={t('signin.create_btn', 'Create account')} onPress={signUp} loading={busy === 'signup'} disabled={name.trim().length < 2 || !isEmail(email) || !strongEnough(password)} />
            )}
          </>
        ) : null}

        {step === 'forgot' ? (
          <>
            <Field label={t('signin.email', 'Email')} icon="globe" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoComplete="email" placeholder="you@example.com" />
            <Button title={t('signin.send_reset', 'Send reset code')} onPress={forgot} loading={busy === 'forgot'} disabled={!isEmail(email)} />
          </>
        ) : null}

        {step === 'verify' || step === 'reset' ? (
          <>
            <CodeBoxes code={code} setCode={setCode} inputRef={codeRef} onDone={step === 'verify' ? verifySignUp : undefined} />
            {resendIn > 0 ? (
              <Text variant="caption" muted>
                {t('signin.resend_in', 'Resend code in {time}', { time: `0:${String(resendIn).padStart(2, '0')}` })}
              </Text>
            ) : (
              <Pressable onPress={resend} accessibilityRole="button" hitSlop={8} disabled={busy === 'resend'}>
                <Text variant="smallStrong" color={c.teal}>
                  {t('signin.resend', 'Send a new code')}
                </Text>
              </Pressable>
            )}
            <Text variant="caption" muted>
              {t('signin.spam_hint', 'Can’t find it? Check your spam or promotions folder.')}
            </Text>
            {sent?.debug_code ? (
              <View style={{ backgroundColor: c.sandBg, borderRadius: 12, padding: 10 }}>
                <Text variant="caption" color={c.sand}>
                  Development mode — code: {sent.debug_code}
                </Text>
              </View>
            ) : null}
            {step === 'reset' ? (
              <PasswordField label={t('signin.new_password', 'New password')} value={password} onChangeText={setPassword} isNew hint={t('signin.password_hint', 'At least 8 characters, with letters and numbers.')} />
            ) : null}
            {step === 'verify' ? (
              <Button title={t('signin.verify', 'Verify & continue')} onPress={verifySignUp} loading={busy === 'verify'} disabled={code.length !== 6} />
            ) : (
              <Button title={t('signin.reset_btn', 'Save password & sign in')} onPress={resetPassword} loading={busy === 'reset'} disabled={code.length !== 6 || !strongEnough(password)} />
            )}
          </>
        ) : null}

        {error ? (
          <View style={{ backgroundColor: c.dangerBg, borderRadius: 12, padding: 12 }} accessibilityLiveRegion="polite">
            <Text variant="small" color={c.danger}>
              {error}
            </Text>
          </View>
        ) : null}

        {step === 'form' ? (
          <>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 }}>
              <View style={{ flex: 1, height: 1, backgroundColor: c.field }} />
              <Text variant="caption" muted>
                {t('signin.or', 'or continue with')}
              </Text>
              <View style={{ flex: 1, height: 1, backgroundColor: c.field }} />
            </View>
            <Pressable
              accessibilityRole="button"
              onPress={telegram}
              disabled={!!busy}
              style={({ pressed }) => ({ height: 52, borderRadius: 14, backgroundColor: '#229ED9', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, opacity: pressed || busy ? 0.8 : 1 })}
            >
              <TelegramLogo />
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 15 }} color="#FFFFFF">
                {busy === 'telegram' ? t('common.please_wait', 'Please wait…') : t('signin.telegram', 'Continue with Telegram')}
              </Text>
            </Pressable>
            {appleAvailable ? (
              <AppleAuthentication.AppleAuthenticationButton
                buttonType={AppleAuthentication.AppleAuthenticationButtonType.CONTINUE}
                buttonStyle={AppleAuthentication.AppleAuthenticationButtonStyle.BLACK}
                cornerRadius={14}
                style={{ height: 52 }}
                onPress={apple}
              />
            ) : null}

            <Text variant="small" muted align="center" style={{ marginTop: 4 }}>
              {mode === 'signin' ? t('signin.no_account', 'Don’t have an account?') : t('signin.have_account', 'Already have an account?')}{' '}
              <Text variant="smallStrong" color={c.teal} onPress={() => switchMode(mode === 'signin' ? 'signup' : 'signin')} accessibilityRole="link">
                {mode === 'signin' ? t('signin.create_one', 'Create one — it’s free') : t('signin.tab_signin', 'Sign in')}
              </Text>
            </Text>

            <View style={{ flex: 1, minHeight: 8 }} />
            <Button variant="soft" size="md" title={t('signin.guest', 'Browse as guest · {count} free questions', { count: freeQuestions })} onPress={guest} loading={busy === 'guest'} />
            <Text variant="caption" muted align="center" style={{ fontSize: 11, lineHeight: 15 }}>
              {t('signin.terms_prefix', 'By continuing you agree to the')}{' '}
              <Text variant="caption" color={c.teal} style={{ fontSize: 11 }} onPress={() => config.data && WebBrowser.openBrowserAsync(config.data.legal.terms_url)}>
                {t('signin.terms', 'Terms')}
              </Text>{' '}
              {t('signin.and', 'and')}{' '}
              <Text variant="caption" color={c.teal} style={{ fontSize: 11 }} onPress={() => config.data && WebBrowser.openBrowserAsync(config.data.legal.privacy_url)}>
                {t('signin.privacy', 'Privacy policy')}
              </Text>
              . {t('signin.devices_note', 'One account can be used on 2 devices.')}
            </Text>
          </>
        ) : null}
      </Screen>

      <DeviceLimitSheet limit={limit} onClose={() => setLimit(null)} />
    </KeyboardAvoidingView>
  );
}

function Field({ label, icon, right, hint, ...input }: TextInputProps & { label: string; icon: 'user' | 'globe' | 'lock'; right?: ReactNode; hint?: string }) {
  const { c } = useTheme();
  const [focused, setFocused] = useState(false);
  return (
    <View style={{ gap: 6 }}>
      <Text variant="smallStrong">{label}</Text>
      <View style={{ height: 54, borderRadius: radius.lg, backgroundColor: c.card, borderWidth: 1.5, borderColor: focused ? c.text : c.field, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 10 }}>
        <Icon name={icon} size={18} color={c.muted} />
        <TextInput
          {...input}
          accessibilityLabel={label}
          placeholderTextColor={c.placeholder}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          style={{ flex: 1, height: '100%', fontFamily: fonts.body, fontSize: 16, color: c.text }}
        />
        {right}
      </View>
      {hint ? (
        <Text variant="caption" muted>
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

function PasswordField({ label, isNew, hint, ...input }: TextInputProps & { label: string; isNew?: boolean; hint?: string }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const [show, setShow] = useState(false);
  return (
    <Field
      {...input}
      label={label}
      icon="lock"
      hint={hint}
      secureTextEntry={!show}
      autoCapitalize="none"
      autoCorrect={false}
      autoComplete={isNew ? 'new-password' : 'current-password'}
      textContentType={isNew ? 'newPassword' : 'password'}
      placeholder="••••••••"
      right={
        <Pressable onPress={() => setShow(!show)} accessibilityRole="button" accessibilityLabel={show ? t('signin.hide_password', 'Hide password') : t('signin.show_password', 'Show password')} hitSlop={10}>
          <Icon name="eye" size={18} color={show ? c.teal : c.muted} />
        </Pressable>
      }
    />
  );
}

function CodeBoxes({ code, setCode, inputRef, onDone }: { code: string; setCode: (v: string) => void; inputRef: React.RefObject<ComponentRef<typeof TextInput> | null>; onDone?: () => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  return (
    <>
      <Pressable onPress={() => inputRef.current?.focus()} accessibilityLabel={t('signin.code_label', '6-digit code')} style={{ flexDirection: 'row', gap: 8 }}>
        {Array.from({ length: 6 }).map((_, i) => {
          const active = i === Math.min(code.length, 5);
          return (
            <View key={i} style={{ flex: 1, height: 56, borderRadius: 14, backgroundColor: c.card, borderWidth: 1.5, borderColor: active ? c.text : c.line, alignItems: 'center', justifyContent: 'center' }}>
              <Text style={{ fontFamily: fonts.mono, fontSize: 22 }}>{code[i] ?? ''}</Text>
            </View>
          );
        })}
      </Pressable>
      <TextInput
        ref={inputRef}
        value={code}
        onChangeText={(v) => setCode(v.replace(/\D/g, '').slice(0, 6))}
        keyboardType="number-pad"
        textContentType="oneTimeCode"
        autoComplete="one-time-code"
        maxLength={6}
        style={{ position: 'absolute', opacity: 0, height: 1, width: 1 }}
        onSubmitEditing={onDone}
      />
    </>
  );
}

/** Telegram paper-plane mark (white, for the blue button). */
function TelegramLogo() {
  return (
    <Svg width={20} height={20} viewBox="0 0 24 24" accessible={false}>
      <Path d="M21.9 4.3 18.7 19.4c-.2 1.1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-5 9.2-8.3c.4-.4-.1-.6-.6-.2L6.1 13l-4.9-1.5c-1.1-.3-1.1-1.1.2-1.6L20.5 2.5c.9-.3 1.7.2 1.4 1.8z" fill="#FFFFFF" />
    </Svg>
  );
}

/** "This account is used on 2 devices" — pick one to sign out. */
function DeviceLimitSheet({ limit, onClose }: { limit: (DeviceLimitError & { retry: (id: number) => void }) | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={!!limit} transparent animationType="slide" onRequestClose={onClose}>
      <Pressable style={{ flex: 1, backgroundColor: c.overlay }} onPress={onClose} accessibilityLabel={t('common.close', 'Close')} />
      <View style={{ backgroundColor: c.card, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 22, paddingBottom: insets.bottom + 22, gap: 14 }}>
        <Text variant="h2">{t('signin.limit_title', 'Your account is on {count} phones', { count: limit?.limit ?? 2 })}</Text>
        <Text variant="small" muted>
          {t('signin.limit_body', 'Choose a phone to sign out so you can continue here.')}
        </Text>
        {limit?.devices.map((d) => (
          <Pressable
            key={d.id}
            accessibilityRole="button"
            onPress={() => limit.retry(d.id)}
            style={({ pressed }) => ({ minHeight: 60, borderRadius: 16, borderWidth: 1, borderColor: c.line, padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12, opacity: pressed ? 0.7 : 1 })}
          >
            <Icon name="phone" size={20} color={c.text} />
            <View style={{ flex: 1 }}>
              <Text variant="bodyStrong">{d.name || d.platform}</Text>
              <Text variant="caption" muted>
                {d.last_seen_at ? t('signin.last_seen', 'Last used {date}', { date: new Date(d.last_seen_at).toLocaleDateString() }) : d.platform}
              </Text>
            </View>
            <Text variant="smallStrong" color={c.stop}>
              {t('signin.sign_out_device', 'Sign out')}
            </Text>
          </Pressable>
        ))}
      </View>
    </Modal>
  );
}
