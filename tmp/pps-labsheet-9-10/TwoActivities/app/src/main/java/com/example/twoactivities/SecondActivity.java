package com.example.twoactivities;

import android.content.Intent;
import android.os.Bundle;
import android.widget.TextView;

import androidx.appcompat.app.ActionBar;
import androidx.appcompat.app.AppCompatActivity;

public class SecondActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_second);

        ActionBar actionBar = getSupportActionBar();
        if (actionBar != null) {
            actionBar.setDisplayHomeAsUpEnabled(true);
        }

        Intent intent = getIntent();
        int textId = intent.getIntExtra(MainActivity.EXTRA_TEXT_ID, 1);

        TextView sectionLabel = findViewById(R.id.text_section_label);
        TextView sectionTitle = findViewById(R.id.text_section_title);
        TextView sectionBody = findViewById(R.id.text_section_body);

        if (textId == 2) {
            sectionLabel.setText(R.string.section_two_label);
            sectionTitle.setText(R.string.section_two_title);
            sectionBody.setText(R.string.section_two_body);
        } else if (textId == 3) {
            sectionLabel.setText(R.string.section_three_label);
            sectionTitle.setText(R.string.section_three_title);
            sectionBody.setText(R.string.section_three_body);
        } else {
            sectionLabel.setText(R.string.section_one_label);
            sectionTitle.setText(R.string.section_one_title);
            sectionBody.setText(R.string.section_one_body);
        }
    }
}
