{{- define "career2.labels" -}}
app.kubernetes.io/name: career2
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{- define "career2.selectorLabels" -}}
app.kubernetes.io/name: career2
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Общий блок env: инфра-секреты postgres/redis + конфигурация приложения.
Подключается через {{- include "career2.env" . | nindent 12 }} в deployment-шаблонах.
*/}}
{{- define "career2.env" -}}
- name: APP_KEY
  valueFrom:
    secretKeyRef:
      name: {{ .Release.Name }}-app
      key: APP_KEY
- name: APP_ENV
  value: {{ .Values.app.env | quote }}
- name: APP_DEBUG
  value: {{ .Values.app.debug | quote }}
- name: APP_URL
  value: {{ .Values.app.url | quote }}
- name: LOG_CHANNEL
  value: {{ .Values.app.logChannel | quote }}
- name: LOG_LEVEL
  value: {{ .Values.app.logLevel | quote }}
- name: TRUSTED_PROXIES
  value: {{ .Values.app.trustedProxies | quote }}
- name: STATS_SELF_SITE_PUBLIC_ID
  value: {{ .Values.app.statsPublicId | quote }}
- name: STATS_SYSTEM_USER_EMAIL
  value: {{ .Values.app.systemUserEmail | default "system@localhost" | quote }}
- name: STATS_SYSTEM_USER_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Release.Name }}-app
      key: STATS_SYSTEM_USER_PASSWORD
- name: MAIL_MAILER
  value: {{ .Values.app.mail.mailer | quote }}
- name: MAIL_HOST
  value: {{ .Values.app.mail.host | quote }}
- name: MAIL_PORT
  value: {{ .Values.app.mail.port | quote }}
- name: MAIL_FROM_ADDRESS
  value: {{ .Values.app.mail.fromAddress | quote }}
- name: MAIL_FROM_NAME
  value: {{ .Values.app.mail.fromName | quote }}
- name: DB_CONNECTION
  value: pgsql
- name: DB_HOST
  valueFrom:
    secretKeyRef:
      name: {{ .Values.postgres.secretName }}
      key: PGHOST
- name: DB_PORT
  valueFrom:
    secretKeyRef:
      name: {{ .Values.postgres.secretName }}
      key: PGPORT
- name: DB_DATABASE
  valueFrom:
    secretKeyRef:
      name: {{ .Values.postgres.secretName }}
      key: PGDATABASE
- name: DB_USERNAME
  valueFrom:
    secretKeyRef:
      name: {{ .Values.postgres.secretName }}
      key: PGUSER
- name: DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.postgres.secretName }}
      key: PGPASSWORD
- name: REDIS_CLIENT
  value: phpredis
- name: REDIS_HOST
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.secretName }}
      key: REDIS_HOST
- name: REDIS_PORT
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.secretName }}
      key: REDIS_PORT
- name: REDIS_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.secretName }}
      key: REDIS_PASSWORD
- name: REDIS_USERNAME
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.secretName }}
      key: REDIS_USERNAME
- name: REDIS_DB
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.secretName }}
      key: REDIS_DB
- name: REDIS_PREFIX
  valueFrom:
    secretKeyRef:
      name: {{ .Values.redis.secretName }}
      key: REDIS_KEY_PREFIX
- name: CACHE_STORE
  value: redis
- name: SESSION_DRIVER
  value: redis
- name: QUEUE_CONNECTION
  value: redis
- name: BROADCAST_CONNECTION
  value: log
- name: FILESYSTEM_DISK
  value: local
{{- end }}
