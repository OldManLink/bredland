# Resolution hooks

`resolutions.json` configures optional pre-action hooks for trusted resolutions.

For BRD-032 it is used to arm Rapid Polling Instrumentation before the RouterOS update action is sent.

The example file lives in:

```text
config/resolutions.example.json
````

On Bredland, install it as:

```text
/etc/bredland/resolutions.json
```

It must be owned by:

```text
bredland-trusted:bredland-trusted
```

A suitable install command is:

```bash
sudo install \
  -o bredland-trusted \
  -g bredland-trusted \
  -m 600 \
  examples/resolutions.json \
  /etc/bredland/resolutions.json
```

If the file is absent, no pre-action resolution hook is configured.

